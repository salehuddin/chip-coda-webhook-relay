<?php
/**
 * WebhookRelay class for Chip-to-Coda Webhook Relay
 * Core functionality to receive, process, and forward webhooks
 */

class WebhookRelay {
    private $config;
    private $logger;
    private $validator;
    
    public function __construct($config, $logger) {
        $this->config = $config;
        $this->logger = $logger;
        $this->validator = new Validator($config, $logger);
    }
    
    /**
     * Process incoming webhook request
     */
    public function processWebhook() {
        $startTime = microtime(true);
        
        try {
            $this->logger->info('Webhook processing started', [
                'remote_ip' => $this->validator->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
            // Validate request
            if (!$this->validator->validateRequest()) {
                return $this->sendErrorResponse(400, 'Invalid request');
            }
            
            // Get payload and headers
            $payload = $this->validator->getPayload();
            $headers = $this->validator->getHeaders();
            
            $this->logger->debug('Incoming webhook received', [
                'payload_size' => strlen($payload),
                'content_type' => $headers['content-type'] ?? 'unknown',
                'headers_count' => count($headers)
            ]);
            
            // Forward to Coda.io
            $response = $this->forwardToCoda($payload, $headers);
            
            $duration = microtime(true) - $startTime;
            
            $this->logger->info('Webhook processing completed', [
                'duration_ms' => round($duration * 1000, 2),
                'coda_response_code' => $response['status_code'],
                'success' => $response['status_code'] < 400
            ]);
            
            // Return appropriate response to Chip
            if ($response['status_code'] < 400) {
                return $this->sendSuccessResponse('Webhook forwarded successfully');
            } else {
                return $this->sendErrorResponse(502, 'Failed to forward webhook to Coda.io');
            }
            
        } catch (Exception $e) {
            $duration = microtime(true) - $startTime;
            
            $this->logger->exception($e, 'Webhook processing failed');
            $this->logger->error('Webhook processing error', [
                'duration_ms' => round($duration * 1000, 2),
                'error_message' => $e->getMessage()
            ]);
            
            return $this->sendErrorResponse(500, 'Internal server error');
        }
    }
    
    /**
     * Forward webhook payload to Coda.io with retries
     */
    private function forwardToCoda($payload, $incomingHeaders) {
        $codaUrl = $this->config->get('coda_webhook_url');
        $bearerToken = $this->config->get('coda_bearer_token');
        $maxAttempts = $this->config->get('retry_attempts', 3);
        $retryDelay = $this->config->get('retry_delay', 1);
        
        $headers = [
            'Authorization: Bearer ' . $bearerToken,
            'Content-Type: ' . ($incomingHeaders['content-type'] ?? 'application/json'),
            'Content-Length: ' . strlen($payload),
            'User-Agent: Chip-to-Coda-Relay/1.0'
        ];
        
        // Add any custom headers that should be forwarded
        $forwardHeaders = $this->getHeadersToForward($incomingHeaders);
        foreach ($forwardHeaders as $header) {
            $headers[] = $header;
        }
        
        $lastError = null;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $requestStart = microtime(true);
            
            try {
                $this->logger->debug('Attempting to forward to Coda.io', [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'url' => $codaUrl
                ]);
                
                $response = $this->makeHttpRequest($codaUrl, $payload, $headers);
                $duration = microtime(true) - $requestStart;
                
                $this->logger->logResponse(
                    $response['status_code'],
                    $response['headers'],
                    $response['body'],
                    $duration
                );
                
                // Success or client error (don't retry client errors)
                if ($response['status_code'] < 500) {
                    return $response;
                }
                
                $lastError = "HTTP {$response['status_code']}: {$response['body']}";
                
            } catch (Exception $e) {
                $duration = microtime(true) - $requestStart;
                $lastError = $e->getMessage();
                
                $this->logger->error('HTTP request failed', [
                    'attempt' => $attempt,
                    'duration_ms' => round($duration * 1000, 2),
                    'error' => $lastError
                ]);
            }
            
            // Don't sleep after the last attempt
            if ($attempt < $maxAttempts) {
                $sleepTime = $retryDelay * $attempt; // Exponential backoff
                $this->logger->debug('Retrying after delay', [
                    'delay_seconds' => $sleepTime,
                    'next_attempt' => $attempt + 1
                ]);
                sleep($sleepTime);
            }
        }
        
        // All attempts failed
        $this->logger->error('All forwarding attempts failed', [
            'attempts' => $maxAttempts,
            'last_error' => $lastError
        ]);
        
        return [
            'status_code' => 502,
            'headers' => [],
            'body' => 'Failed to forward after ' . $maxAttempts . ' attempts: ' . $lastError
        ];
    }
    
    /**
     * Make HTTP request using cURL
     */
    private function makeHttpRequest($url, $payload, $headers) {
        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $this->config->get('request_timeout', 30),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => $this->config->get('ssl_verify', true),
            CURLOPT_SSL_VERIFYHOST => $this->config->get('ssl_verify', true) ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_MAXREDIRS => 0
        ];
        
        curl_setopt_array($ch, $options);
        
        $this->logger->logRequest('POST', $url, $headers, $payload);
        
        $response = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception('cURL error: ' . $error);
        }
        
        curl_close($ch);
        
        // Parse response
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        return [
            'status_code' => $statusCode,
            'headers' => $this->parseHeaders($responseHeaders),
            'body' => $responseBody
        ];
    }
    
    /**
     * Get headers that should be forwarded to Coda.io
     */
    private function getHeadersToForward($incomingHeaders) {
        $forwardHeaders = [];
        
        // Headers that should be preserved
        $preserveHeaders = [
            'x-forwarded-for',
            'x-real-ip',
            'x-request-id',
            'x-correlation-id'
        ];
        
        foreach ($preserveHeaders as $header) {
            if (isset($incomingHeaders[$header])) {
                $forwardHeaders[] = ucfirst($header) . ': ' . $incomingHeaders[$header];
            }
        }
        
        // Add original source information
        $forwardHeaders[] = 'X-Forwarded-By: Chip-to-Coda-Relay';
        $forwardHeaders[] = 'X-Original-Source: ' . $this->validator->getClientIP();
        
        return $forwardHeaders;
    }
    
    /**
     * Parse HTTP response headers
     */
    private function parseHeaders($headerString) {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[strtolower(trim($key))] = trim($value);
            }
        }
        
        return $headers;
    }
    
    /**
     * Handle health check requests
     */
    public function handleHealthCheck() {
        try {
            $config = $this->config->getAll(false); // Don't include sensitive data
            $status = [
                'status' => 'healthy',
                'timestamp' => date('c'),
                'version' => $config['app_version'],
                'environment' => $config['app_environment'],
                'configuration' => [
                    'coda_webhook_configured' => !empty($config['coda_webhook_url']),
                    'bearer_token_configured' => !empty($config['coda_bearer_token']),
                    'signature_verification' => $config['signature_verification'],
                    'log_level' => $config['log_level'],
                    'max_payload_size' => $config['max_payload_size']
                ]
            ];
            
            // Test Coda.io connectivity (optional)
            if (isset($_GET['test_connection']) && $_GET['test_connection'] === '1') {
                $status['connectivity'] = $this->testCodaConnectivity();
            }
            
            $this->logger->debug('Health check requested', $status);
            
            return $this->sendJsonResponse($status, 200);
            
        } catch (Exception $e) {
            $this->logger->exception($e, 'Health check failed');
            
            return $this->sendJsonResponse([
                'status' => 'unhealthy',
                'timestamp' => date('c'),
                'error' => 'Configuration error'
            ], 500);
        }
    }
    
    /**
     * Test connectivity to Coda.io (without sending data)
     */
    private function testCodaConnectivity() {
        try {
            $url = $this->config->get('coda_webhook_url');
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_NOBODY => true, // HEAD request only
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => $this->config->get('ssl_verify', true)
            ]);
            
            curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if (!empty($error)) {
                return ['status' => 'error', 'message' => $error];
            }
            
            return ['status' => 'ok', 'http_code' => $statusCode];
            
        } catch (Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        return true;
    }
    
    /**
     * Send success response
     */
    private function sendSuccessResponse($message) {
        return $this->sendJsonResponse([
            'success' => true,
            'message' => $message,
            'timestamp' => date('c')
        ], 200);
    }
    
    /**
     * Send error response
     */
    private function sendErrorResponse($statusCode, $message) {
        return $this->sendJsonResponse([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ], $statusCode);
    }
}
