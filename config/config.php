<?php
/**
 * Configuration loader for Chip-to-Coda Webhook Relay
 * Loads settings from environment variables and provides defaults
 */

class Config {
    private static $instance = null;
    private $settings = [];

    private function __construct() {
        $this->loadEnvironment();
        $this->loadSettings();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    /**
     * Load environment variables from .env file if it exists
     */
    private function loadEnvironment() {
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Parse key=value pairs
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    $value = trim($value, '"\'');
                    
                    // Only set if not already in environment
                    if (!isset($_ENV[$key])) {
                        $_ENV[$key] = $value;
                    }
                }
            }
        }
    }

    /**
     * Load all configuration settings
     */
    private function loadSettings() {
        $this->settings = [
            // Required settings
            'coda_bearer_token' => $this->getEnv('CODA_BEARER_TOKEN'),
            'coda_webhook_url' => $this->getEnv('CODA_WEBHOOK_URL'),
            
            // Optional settings with defaults
            'chip_webhook_secret' => $this->getEnv('CHIP_WEBHOOK_SECRET', ''),
            'chip_public_key' => $this->getEnv('CHIP_PUBLIC_KEY', ''),
            'log_level' => strtoupper($this->getEnv('LOG_LEVEL', 'INFO')),
            
            // Technical settings
            'request_timeout' => (int)$this->getEnv('REQUEST_TIMEOUT', 30),
            'retry_attempts' => (int)$this->getEnv('RETRY_ATTEMPTS', 3),
            'retry_delay' => (int)$this->getEnv('RETRY_DELAY', 1),
            'ssl_verify' => filter_var($this->getEnv('SSL_VERIFY', 'true'), FILTER_VALIDATE_BOOLEAN),
            'max_payload_size' => (int)$this->getEnv('MAX_PAYLOAD_SIZE', 1048576), // 1MB default
            
            // Security settings
            'allowed_ips' => array_filter(explode(',', $this->getEnv('ALLOWED_IPS', ''))),
            'signature_verification' => filter_var($this->getEnv('SIGNATURE_VERIFICATION', 'false'), FILTER_VALIDATE_BOOLEAN),
            
            // Logging settings
            'log_directory' => $this->getEnv('LOG_DIRECTORY', dirname(__DIR__) . '/logs'),
            'log_rotation_days' => (int)$this->getEnv('LOG_ROTATION_DAYS', 30),
            
            // Application settings
            'app_name' => 'Chip-to-Coda Webhook Relay',
            'app_version' => '1.0.0',
            'app_environment' => $this->getEnv('APP_ENVIRONMENT', 'production'),
        ];
        
        // Validate required settings
        $this->validateRequired();
    }

    /**
     * Get environment variable with optional default
     */
    private function getEnv($key, $default = null) {
        // Check $_ENV first, then getenv()
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        return $value !== false ? $value : $default;
    }

    /**
     * Validate that required configuration is present
     */
    private function validateRequired() {
        $required = ['coda_bearer_token', 'coda_webhook_url'];
        $missing = [];
        
        foreach ($required as $key) {
            if (empty($this->settings[$key])) {
                $missing[] = strtoupper($key);
            }
        }
        
        if (!empty($missing)) {
            throw new Exception('Missing required configuration: ' . implode(', ', $missing));
        }
        
        // Validate URLs
        if (!filter_var($this->settings['coda_webhook_url'], FILTER_VALIDATE_URL)) {
            throw new Exception('Invalid CODA_WEBHOOK_URL format');
        }
    }

    /**
     * Get configuration value
     */
    public function get($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }

    /**
     * Get all configuration settings (excluding sensitive data)
     */
    public function getAll($includeSensitive = false) {
        $config = $this->settings;
        
        if (!$includeSensitive) {
            // Mask sensitive values
            $sensitive = ['coda_bearer_token', 'chip_webhook_secret', 'chip_public_key'];
            foreach ($sensitive as $key) {
                if (isset($config[$key]) && !empty($config[$key])) {
                    $config[$key] = '***masked***';
                }
            }
        }
        
        return $config;
    }

    /**
     * Check if the application is in debug mode
     */
    public function isDebug() {
        return $this->get('log_level') === 'DEBUG';
    }

    /**
     * Check if signature verification is enabled
     */
    public function isSignatureVerificationEnabled() {
        return $this->get('signature_verification') && 
               (!empty($this->get('chip_webhook_secret')) || !empty($this->get('chip_public_key')));
    }

    /**
     * Get log file path for specific type
     */
    public function getLogPath($type = 'webhook') {
        $logDir = $this->get('log_directory');
        
        // Create log directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Use .txt extension for WordPress/LiteSpeed compatibility
        $extension = $this->get('log_file_extension', 'txt');
        return $logDir . '/' . $type . '.' . $extension;
    }
}
