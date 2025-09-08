<?php
/**
 * Logger class for Chip-to-Coda Webhook Relay
 * Provides comprehensive logging with multiple levels and file rotation
 */

class Logger {
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    
    private static $instance = null;
    private $config;
    private $levels = [
        self::LEVEL_DEBUG => 0,
        self::LEVEL_INFO => 1,
        self::LEVEL_WARNING => 2,
        self::LEVEL_ERROR => 3
    ];
    
    private function __construct($config) {
        $this->config = $config;
        $this->rotateLogsIfNeeded();
    }
    
    public static function getInstance($config = null) {
        if (self::$instance === null) {
            if ($config === null) {
                throw new Exception('Logger requires configuration on first initialization');
            }
            self::$instance = new Logger($config);
        }
        return self::$instance;
    }
    
    /**
     * Log debug message
     */
    public function debug($message, $context = []) {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     */
    public function info($message, $context = []) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log warning message
     */
    public function warning($message, $context = []) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log error message
     */
    public function error($message, $context = []) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log exception with full stack trace
     */
    public function exception($exception, $message = '') {
        $context = [
            'exception_class' => get_class($exception),
            'exception_message' => $exception->getMessage(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'stack_trace' => $exception->getTraceAsString()
        ];
        
        $logMessage = $message ?: 'Exception occurred';
        $this->error($logMessage, $context);
    }
    
    /**
     * Log HTTP request details
     */
    public function logRequest($method, $url, $headers = [], $body = '', $response = null) {
        $context = [
            'method' => $method,
            'url' => $url,
            'headers' => $this->sanitizeHeaders($headers),
            'body_length' => strlen($body),
            'timestamp' => microtime(true)
        ];
        
        // Only log body content in debug mode and if it's not too large
        if ($this->shouldLogBody()) {
            $context['body'] = $this->sanitizeBody($body);
        }
        
        if ($response !== null) {
            $context['response'] = $this->sanitizeResponse($response);
        }
        
        $this->debug('HTTP Request', $context);
    }
    
    /**
     * Log HTTP response details
     */
    public function logResponse($statusCode, $headers = [], $body = '', $duration = null) {
        $context = [
            'status_code' => $statusCode,
            'headers' => $this->sanitizeHeaders($headers),
            'body_length' => strlen($body)
        ];
        
        if ($duration !== null) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }
        
        // Only log body content in debug mode and if it's not too large
        if ($this->shouldLogBody()) {
            $context['body'] = $this->sanitizeBody($body);
        }
        
        $level = $statusCode >= 400 ? self::LEVEL_ERROR : self::LEVEL_DEBUG;
        $this->log($level, 'HTTP Response', $context);
    }
    
    /**
     * Core logging method
     */
    private function log($level, $message, $context = []) {
        // Check if this level should be logged
        if (!$this->shouldLog($level)) {
            return;
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $pid = getmypid();
        $memory = $this->formatBytes(memory_get_usage(true));
        
        // Build log entry
        $logEntry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'pid' => $pid,
            'memory' => $memory,
            'message' => $message
        ];
        
        if (!empty($context)) {
            $logEntry['context'] = $context;
        }
        
        // Format and write log entry
        $formattedEntry = $this->formatLogEntry($logEntry);
        $this->writeLogEntry($formattedEntry, $level);
    }
    
    /**
     * Check if a log level should be logged based on configuration
     */
    private function shouldLog($level) {
        $configLevel = $this->config->get('log_level', self::LEVEL_INFO);
        $currentLevelNum = $this->levels[$level] ?? 0;
        $configLevelNum = $this->levels[$configLevel] ?? 1;
        
        return $currentLevelNum >= $configLevelNum;
    }
    
    /**
     * Check if request/response bodies should be logged (debug mode only)
     */
    private function shouldLogBody() {
        return $this->config->isDebug();
    }
    
    /**
     * Format log entry as JSON
     */
    private function formatLogEntry($entry) {
        return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    /**
     * Write log entry to appropriate file
     */
    private function writeLogEntry($entry, $level) {
        try {
            // Determine log file based on level
            $logType = in_array($level, [self::LEVEL_ERROR]) ? 'error' : 'webhook';
            $logFile = $this->config->getLogPath($logType);
            
            // Ensure directory exists
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            // Write to log file
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
            
        } catch (Exception $e) {
            // Fallback to error_log if file writing fails
            error_log("Logger write failed: " . $e->getMessage());
            error_log($entry);
        }
    }
    
    /**
     * Sanitize headers to remove sensitive information
     */
    private function sanitizeHeaders($headers) {
        $sanitized = [];
        $sensitiveHeaders = ['authorization', 'x-api-key', 'cookie'];
        
        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $sensitiveHeaders)) {
                $sanitized[$key] = '***masked***';
            } else {
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize request/response body
     */
    private function sanitizeBody($body) {
        // Truncate large bodies
        $maxLength = 4096; // 4KB limit for logging
        if (strlen($body) > $maxLength) {
            return substr($body, 0, $maxLength) . '... [truncated]';
        }
        
        // Try to decode JSON and mask sensitive fields
        $data = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->maskSensitiveData($data);
        }
        
        return $body;
    }
    
    /**
     * Sanitize response data
     */
    private function sanitizeResponse($response) {
        if (is_array($response)) {
            return $this->maskSensitiveData($response);
        }
        
        return $response;
    }
    
    /**
     * Mask sensitive data in arrays
     */
    private function maskSensitiveData($data) {
        if (!is_array($data)) {
            return $data;
        }
        
        $sensitiveKeys = ['token', 'password', 'secret', 'key', 'authorization'];
        
        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);
            $isSensitive = false;
            
            foreach ($sensitiveKeys as $sensitiveKey) {
                if (strpos($lowerKey, $sensitiveKey) !== false) {
                    $isSensitive = true;
                    break;
                }
            }
            
            if ($isSensitive) {
                $data[$key] = '***masked***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }
        
        return $data;
    }
    
    /**
     * Format bytes for memory usage display
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Rotate logs if they're too old
     */
    private function rotateLogsIfNeeded() {
        $rotationDays = $this->config->get('log_rotation_days', 30);
        if ($rotationDays <= 0) {
            return; // Rotation disabled
        }
        
        $logTypes = ['webhook', 'error'];
        $cutoffTime = time() - ($rotationDays * 24 * 60 * 60);
        
        foreach ($logTypes as $type) {
            try {
                $logFile = $this->config->getLogPath($type);
                
                if (file_exists($logFile) && filemtime($logFile) < $cutoffTime) {
                    // Archive old log
                    $archiveName = $logFile . '.' . date('Y-m-d', filemtime($logFile));
                    if (!file_exists($archiveName)) {
                        rename($logFile, $archiveName);
                    }
                }
            } catch (Exception $e) {
                // Ignore rotation errors
            }
        }
    }
    
    /**
     * Get recent log entries for debugging
     */
    public function getRecentLogs($type = 'webhook', $lines = 100) {
        $logFile = $this->config->getLogPath($type);
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        try {
            $command = "tail -n {$lines} " . escapeshellarg($logFile);
            $output = shell_exec($command);
            
            if ($output === null) {
                // Fallback for systems without tail command
                $lines = file($logFile);
                $output = implode('', array_slice($lines, -$lines));
            }
            
            $entries = [];
            $logLines = explode("\n", trim($output));
            
            foreach ($logLines as $line) {
                if (empty($line)) continue;
                
                $entry = json_decode($line, true);
                if ($entry !== null) {
                    $entries[] = $entry;
                }
            }
            
            return $entries;
            
        } catch (Exception $e) {
            return ['error' => 'Could not retrieve logs: ' . $e->getMessage()];
        }
    }
}
