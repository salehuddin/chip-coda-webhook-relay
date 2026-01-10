<?php
/**
 * Coda Connectivity Test Script
 * 
 * This script tests if the server can successfully connect to Coda.io
 * and send webhook data. Use this to diagnose connectivity issues.
 * 
 * Usage: php test-coda-connection.php
 * 
 * Requirements:
 * - PHP 8.0+ with cURL extension enabled
 * - cacert.pem file in ../src/ directory
 * - Valid Coda API token configured
 */

// Load configuration
require_once __DIR__ . '/../src/Config.php';

try {
    $config = new Config();
    $url = $config->get('coda_webhook_url');
    $token = $config->get('coda_bearer_token');
} catch (Exception $e) {
    // Fallback if Config class not available
    echo "Warning: Could not load config. Using environment variables or defaults.\n";
    $url = getenv('CODA_WEBHOOK_URL') ?: 'https://coda.io/apis/v1/docs/YOUR_DOC_ID/hooks/automation/YOUR_HOOK_ID';
    $token = getenv('CODA_BEARER_TOKEN') ?: 'YOUR_TOKEN_HERE';
}

$caBundlePath = __DIR__ . '/../src/cacert.pem';

echo "=== Coda Connectivity Test ===\n\n";
echo "URL: $url\n";
echo "Token: " . substr($token, 0, 20) . "...\n";
echo "CA Bundle: $caBundlePath\n";
echo "CA Bundle exists: " . (file_exists($caBundlePath) ? 'YES' : 'NO') . "\n\n";

if (!file_exists($caBundlePath)) {
    echo "ERROR: CA bundle not found!\n";
    echo "Download from: https://curl.se/ca/cacert.pem\n";
    echo "Save to: $caBundlePath\n";
    exit(1);
}

// Create test payload
$testPayload = json_encode([
    'id' => 'test-' . time(),
    'event_type' => 'test.connection',
    'reference' => 'TEST-' . date('Ymd-His'),
    'client' => [
        'full_name' => 'Connection Test',
        'email' => 'test@example.com'
    ],
    'status' => 'test'
]);

echo "Sending test request...\n";
$startTime = microtime(true);

$ch = curl_init();

curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $testPayload,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Content-Length: ' . strlen($testPayload)
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_CAINFO => $caBundlePath,
    CURLOPT_VERBOSE => true
]);

// Capture verbose output
$verboseFile = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verboseFile);

$response = curl_exec($ch);
$duration = microtime(true) - $startTime;

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);

// Get verbose output
rewind($verboseFile);
$verboseLog = stream_get_contents($verboseFile);
fclose($verboseFile);

curl_close($ch);

echo "\n=== RESULTS ===\n";
echo "Duration: " . round($duration * 1000, 2) . " ms\n";
echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($error ?: 'None') . "\n\n";

echo "Connection Details:\n";
echo "- Total Time: " . round($info['total_time'] * 1000, 2) . " ms\n";
echo "- Connect Time: " . round($info['connect_time'] * 1000, 2) . " ms\n";
echo "- SSL Verify Result: " . $info['ssl_verify_result'] . " (0 = success)\n";
echo "- Primary IP: " . ($info['primary_ip'] ?? 'N/A') . "\n";

if ($response !== false && $httpCode > 0) {
    echo "\n✅ SUCCESS!\n";
    echo "HTTP Status: $httpCode\n";
    
    // Parse response
    $headerSize = $info['header_size'];
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    echo "\nResponse Body:\n";
    echo $body . "\n";
    
    if ($httpCode == 202) {
        echo "\n🎉 Connection test PASSED!\n";
        echo "The server can successfully communicate with Coda.io\n";
    } elseif ($httpCode == 401 || $httpCode == 403) {
        echo "\n⚠️  Authentication issue - check your API token\n";
    } elseif ($httpCode >= 400) {
        echo "\n⚠️  HTTP error - check the response above\n";
    }
} else {
    echo "\n❌ FAILED!\n";
    echo "Error: $error\n";
    echo "\nPossible causes:\n";
    echo "- Firewall blocking outbound HTTPS\n";
    echo "- DNS resolution issue\n";
    echo "- SSL certificate problem\n";
    echo "- Network routing issue\n";
    echo "- cURL extension not enabled\n";
}

echo "\n=== VERBOSE cURL LOG ===\n";
echo $verboseLog;

echo "\n=== PHP INFO ===\n";
echo "PHP Version: " . phpversion() . "\n";
echo "cURL Version: " . curl_version()['version'] . "\n";
echo "SSL Version: " . curl_version()['ssl_version'] . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Loaded' : 'NOT loaded') . "\n";

// Save results to file
$logFile = __DIR__ . '/test-result.log';
file_put_contents($logFile, 
    "Test run at: " . date('Y-m-d H:i:s') . "\n" .
    "HTTP Code: $httpCode\n" .
    "Error: $error\n" .
    "Duration: " . round($duration * 1000, 2) . " ms\n" .
    "SSL Verify: " . $info['ssl_verify_result'] . "\n\n" .
    "Verbose Log:\n" . $verboseLog . "\n\n" .
    "Response:\n" . $response . "\n"
);

echo "\nResults saved to: $logFile\n";
?>
