<?php
/**
 * Main entry point for Chip-to-Coda Webhook Relay
 * Handles routing and bootstrapping of the application
 */

// Set error reporting for production
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Set timezone
date_default_timezone_set('UTC');

// Include required classes
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/Validator.php';
require_once __DIR__ . '/../src/WebhookRelay.php';

// Handle CORS for development/testing
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
}

// Handle preflight OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    // Initialize configuration
    $config = Config::getInstance();
    
    // Initialize logger
    $logger = Logger::getInstance($config);
    
    // Initialize webhook relay
    $relay = new WebhookRelay($config, $logger);
    
    // Determine the action based on request
    $action = $_GET['action'] ?? 'webhook';
    
    switch ($action) {
        case 'health':
            // Health check endpoint
            $relay->handleHealthCheck();
            break;
            
        case 'webhook':
        default:
            // Main webhook processing
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                // Show simple info page for GET requests
                showInfoPage($config);
            } else {
                // Process the webhook
                $relay->processWebhook();
            }
            break;
    }

} catch (Exception $e) {
    // Log the error if logger is available
    if (isset($logger)) {
        $logger->exception($e, 'Application bootstrap failed');
    } else {
        error_log('Webhook Relay Bootstrap Error: ' . $e->getMessage());
    }
    
    // Return generic error response
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Service temporarily unavailable',
        'timestamp' => date('c')
    ], JSON_PRETTY_PRINT);
}

/**
 * Show information page for GET requests
 */
function showInfoPage($config) {
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    
    $appName = $config->get('app_name', 'Chip-to-Coda Webhook Relay');
    $version = $config->get('app_version', '1.0.0');
    
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$appName}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            line-height: 1.6;
            color: #333;
            background: #f8f9fa;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .status {
            background: #d4edda;
            color: #155724;
            padding: 1rem;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
            margin: 1rem 0;
        }
        .endpoint {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 4px;
            border-left: 4px solid #007bff;
            margin: 1rem 0;
            font-family: monospace;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔗 {$appName}</h1>
        <p>A lightweight middleware service that receives webhook notifications from Chip payment gateway and forwards them to Coda.io with proper Bearer token authentication.</p>
        
        <div class="status">
            ✅ Service is running normally (v{$version})
        </div>
        
        <h2>Endpoints</h2>
        
        <h3>Webhook Endpoint</h3>
        <div class="endpoint">
            POST {$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}
        </div>
        <p>Configure this URL in your Chip payment gateway webhook settings.</p>
        
        <h3>Health Check</h3>
        <div class="endpoint">
            GET {$_SERVER['REQUEST_SCHEME']}://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}?action=health
        </div>
        <p>Returns service status and configuration information.</p>
        
        <h2>How It Works</h2>
        <ol>
            <li>Chip payment gateway sends webhook to this service</li>
            <li>Service validates the request and extracts the payload</li>
            <li>Service adds Bearer token authentication header</li>
            <li>Service forwards the request to Coda.io webhook</li>
            <li>Service returns response status to Chip</li>
        </ol>
        
        <div class="footer">
            <p>For support and documentation, check the project README.md file.</p>
            <p>Request ID: <code><?php echo uniqid(); ?></code> | Timestamp: <?php echo date('c'); ?></p>
        </div>
    </div>
</body>
</html>
HTML;
}
