<?php
/**
 * Validator class for Chip-to-Coda Webhook Relay
 * Handles input validation, security checks, and signature verification
 */

class Validator {
    private $config;
    private $logger;
    
    public function __construct($config, $logger) {
        $this->config = $config;
        $this->logger = $logger;
    }
    
    /**
     * Validate incoming webhook request
     */
    public function validateRequest() {
        $errors = [];
        
        // Check request method
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $errors[] = 'Only POST requests are allowed';
        }
        
        // Check content type
        if (!$this->isValidContentType()) {
            $errors[] = 'Invalid content type';
        }
        
        // Check payload size
        $contentLength = $this->getContentLength();
        $maxSize = $this->config->get('max_payload_size', 1048576); // 1MB default
        
        if ($contentLength > $maxSize) {
            $errors[] = "Payload too large: {$contentLength} bytes (max: {$maxSize})";
        }
        
        // Check IP whitelist if configured
        if (!$this->isAllowedIP()) {
            $errors[] = 'Request from unauthorized IP';
        }
        
        // Verify webhook signature if enabled
        if ($this->config->isSignatureVerificationEnabled()) {
            if (!$this->verifySignature()) {
                $errors[] = 'Invalid webhook signature';
            }
        }
        
        if (!empty($errors)) {
            $this->logger->warning('Request validation failed', [
                'errors' => $errors,
                'remote_ip' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get and validate request payload
     */
    public function getPayload() {
        $rawPayload = file_get_contents('php://input');
        
        if (empty($rawPayload)) {
            throw new Exception('Empty request payload');
        }
        
        // Validate JSON if content type is application/json
        $contentType = $this->getContentType();
        if (strpos($contentType, 'application/json') === 0) {
            $decoded = json_decode($rawPayload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON payload: ' . json_last_error_msg());
            }
        }
        
        return $rawPayload;
    }
    
    /**
     * Get request headers in a normalized format
     */
    public function getHeaders() {
        $headers = [];
        
        // Get headers from $_SERVER
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$header] = $value;
            }
        }
        
        // Add content type and length if available
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
        }
        
        return $headers;
    }
    
    /**
     * Check if content type is valid
     */
    private function isValidContentType() {
        $contentType = $this->getContentType();
        $validTypes = [
            'application/json',
            'application/x-www-form-urlencoded',
            'multipart/form-data'
        ];
        
        foreach ($validTypes as $type) {
            if (strpos($contentType, $type) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get content type from headers
     */
    private function getContentType() {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }
    
    /**
     * Get content length from headers
     */
    private function getContentLength() {
        return (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    }
    
    /**
     * Check if request is from allowed IP
     */
    private function isAllowedIP() {
        $allowedIPs = $this->config->get('allowed_ips', []);
        
        // If no IP restrictions configured, allow all
        if (empty($allowedIPs)) {
            return true;
        }
        
        $clientIP = $this->getClientIP();
        
        foreach ($allowedIPs as $allowedIP) {
            $allowedIP = trim($allowedIP);
            
            // Exact match
            if ($clientIP === $allowedIP) {
                return true;
            }
            
            // CIDR range match (basic implementation)
            if (strpos($allowedIP, '/') !== false) {
                if ($this->ipInRange($clientIP, $allowedIP)) {
                    return true;
                }
            }
        }
        
        $this->logger->warning('Request from unauthorized IP', [
            'client_ip' => $clientIP,
            'allowed_ips' => $allowedIPs
        ]);
        
        return false;
    }
    
    /**
     * Get client IP address, considering proxies
     */
    public function getClientIP() {
        // Check for IP from various headers (in order of preference)
        $ipHeaders = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Most proxies
            'HTTP_X_FORWARDED',          // Some proxies
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Old standard
            'HTTP_FORWARDED',            // RFC 7239
            'HTTP_CLIENT_IP',            // Some proxies
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($ipHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Handle comma-separated IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP format
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        // Fallback to REMOTE_ADDR even if it's private/reserved
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Check if IP is in CIDR range (basic implementation)
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $bits) = explode('/', $range);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        // Convert to binary
        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);
        
        if ($ipBin === false || $subnetBin === false) {
            return false;
        }
        
        // IPv4
        if (strlen($ipBin) === 4) {
            $mask = ~((1 << (32 - $bits)) - 1);
            return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
        }
        
        // IPv6 (simplified)
        if (strlen($ipBin) === 16) {
            $bytesToCheck = intval($bits / 8);
            $bitsToCheck = $bits % 8;
            
            // Check full bytes
            for ($i = 0; $i < $bytesToCheck; $i++) {
                if ($ipBin[$i] !== $subnetBin[$i]) {
                    return false;
                }
            }
            
            // Check partial byte
            if ($bitsToCheck > 0 && $bytesToCheck < 16) {
                $mask = 0xFF << (8 - $bitsToCheck);
                if ((ord($ipBin[$bytesToCheck]) & $mask) !== (ord($subnetBin[$bytesToCheck]) & $mask)) {
                    return false;
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Verify webhook signature (basic HMAC implementation)
     */
    private function verifySignature() {
        $secret = $this->config->get('chip_webhook_secret');
        if (empty($secret)) {
            return true; // No secret configured, skip verification
        }
        
        // Get signature from headers (common header names)
        $signatureHeaders = [
            'x-signature',
            'x-hub-signature',
            'x-hook-signature',
            'signature'
        ];
        
        $providedSignature = null;
        foreach ($signatureHeaders as $header) {
            $headerValue = $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $header))] ?? null;
            if (!empty($headerValue)) {
                $providedSignature = $headerValue;
                break;
            }
        }
        
        if (empty($providedSignature)) {
            $this->logger->warning('Signature verification enabled but no signature provided');
            return false;
        }
        
        // Get request payload
        $payload = file_get_contents('php://input');
        
        // Calculate expected signature (try different algorithms)
        $algorithms = ['sha256', 'sha1', 'md5'];
        
        foreach ($algorithms as $algorithm) {
            $expectedSignature = hash_hmac($algorithm, $payload, $secret);
            $expectedSignatureWithPrefix = $algorithm . '=' . $expectedSignature;
            
            // Compare signatures (time-safe comparison)
            if ($this->hashEquals($providedSignature, $expectedSignature) ||
                $this->hashEquals($providedSignature, $expectedSignatureWithPrefix)) {
                
                $this->logger->debug('Signature verification successful', [
                    'algorithm' => $algorithm
                ]);
                return true;
            }
        }
        
        $this->logger->warning('Signature verification failed', [
            'provided_signature' => $providedSignature,
            'client_ip' => $this->getClientIP()
        ]);
        
        return false;
    }
    
    /**
     * Time-safe string comparison
     */
    private function hashEquals($known, $provided) {
        if (function_exists('hash_equals')) {
            return hash_equals($known, $provided);
        }
        
        // Fallback for older PHP versions
        if (strlen($known) !== strlen($provided)) {
            return false;
        }
        
        $result = 0;
        for ($i = 0; $i < strlen($known); $i++) {
            $result |= ord($known[$i]) ^ ord($provided[$i]);
        }
        
        return $result === 0;
    }
    
    /**
     * Sanitize input data for logging
     */
    public function sanitizeForLogging($data) {
        if (is_string($data)) {
            // Truncate long strings
            if (strlen($data) > 1000) {
                return substr($data, 0, 1000) . '... [truncated]';
            }
            return $data;
        }
        
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitizeForLogging($value);
            }
            return $sanitized;
        }
        
        return $data;
    }
}
