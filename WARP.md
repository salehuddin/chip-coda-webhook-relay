# WARP.md

This file provides guidance to WARP (warp.dev) when working with code in this repository.

## Project Overview

This is a **Chip-to-Coda Webhook Relay** - a lightweight PHP middleware service that bridges the gap between Chip payment gateway webhooks and Coda.io. The core problem it solves is that Chip doesn't support custom headers in webhook requests, but Coda.io requires Bearer token authentication.

## Common Commands

### Development Setup
```powershell
# Copy environment configuration
copy config\.env.example config\.env

# Edit the .env file with your actual tokens and URLs
notepad config\.env
```

### Testing & Health Checks
```powershell
# Test the health endpoint (replace with your actual domain)
curl "https://yourdomain.com/webhook/health"

# Test with connection check
curl "https://yourdomain.com/webhook/health?test_connection=1"

# Test webhook endpoint (replace with test data)
curl -X POST "https://yourdomain.com/webhook/chip" -H "Content-Type: application/json" -d '{\"test\": \"data\"}'
```

### Log Management
```powershell
# View recent webhook logs (if SSH access available)
tail -f logs/webhook.log

# View error logs
tail -f logs/error.log

# Check log directory size
dir logs

# Archive old logs manually (Windows)
move logs\webhook.log logs\webhook.log.bak
```

### Deployment (cPanel/Shared Hosting)
```powershell
# Create deployment package (exclude sensitive files)
# Use your preferred archiving tool, excluding:
# - logs/*
# - config/.env
# - .git/
```

## Architecture & Code Structure

### Request Flow Architecture
```
Chip Gateway → WebhookRelay → Coda.io
     ↓              ↓             ↓
  POST /chip    Add Bearer    Forward with
   payload      Token Header   Auth Header
```

### Core Components

**WebhookRelay** (`src/WebhookRelay.php`)
- Main orchestrator handling the webhook relay process
- Manages retry logic with exponential backoff (3 attempts by default)
- Handles health checks and connectivity testing
- Key methods: `processWebhook()`, `forwardToCoda()`, `handleHealthCheck()`

**Validator** (`src/Validator.php`) 
- Input validation and security enforcement
- IP whitelisting support with CIDR range matching
- Webhook signature verification (both HMAC and RSA/ECDSA public key)
- Request sanitization and payload size limits
- Client IP detection considering various proxy headers

**Logger** (`src/Logger.php`)
- Comprehensive logging with multiple levels (DEBUG, INFO, WARNING, ERROR)
- Automatic log rotation and sensitive data masking
- Structured JSON logging for easy parsing
- Performance monitoring (request duration, memory usage)

**Config** (`config/config.php`)
- Singleton configuration manager
- Environment variable loading from `.env` file
- Required configuration validation
- Sensitive data masking for debugging

### Key Design Patterns

1. **Singleton Pattern**: Used for Config and Logger to ensure single instances
2. **Strategy Pattern**: Multiple signature verification algorithms attempted
3. **Template Method**: Consistent request/response flow with customizable validation
4. **Factory Pattern**: Log entry formatting and file path generation

### Security Considerations

- **Token Protection**: Bearer tokens never logged or exposed in responses
- **Input Validation**: All payloads validated for size, format, and content-type
- **IP Whitelisting**: Optional restriction to specific IP addresses/ranges
- **Signature Verification**: Optional HMAC or RSA/ECDSA verification of webhook authenticity
- **SSL Enforcement**: SSL certificate verification enabled by default

## Configuration Management

### Required Environment Variables
- `CODA_BEARER_TOKEN`: Authentication token for Coda.io
- `CODA_WEBHOOK_URL`: Target Coda.io webhook endpoint

### Optional Security Settings
- `CHIP_WEBHOOK_SECRET`: For HMAC signature verification (legacy)
- `CHIP_PUBLIC_KEY`: For RSA/ECDSA signature verification (recommended)
- `ALLOWED_IPS`: Comma-separated IP whitelist
- `SIGNATURE_VERIFICATION`: Enable/disable signature checking

### Configuring Chip Public Key
To use RSA/ECDSA signature verification (recommended):

1. **Get your public key from Chip** (format: `-----BEGIN PUBLIC KEY-----...-----END PUBLIC KEY-----`)
2. **Add to .env file**:
   ```bash
   CHIP_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----
   MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...
   -----END PUBLIC KEY-----"
   SIGNATURE_VERIFICATION=true
   ```
3. **Alternative format** (if you only have the key content without headers):
   ```bash
   CHIP_PUBLIC_KEY="MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA..."
   ```
   The system will automatically add PEM headers if missing.

### Performance Tuning
- `REQUEST_TIMEOUT`: HTTP request timeout (default: 30s)
- `RETRY_ATTEMPTS`: Number of retry attempts (default: 3)
- `RETRY_DELAY`: Base delay for exponential backoff (default: 1s)
- `MAX_PAYLOAD_SIZE`: Maximum webhook payload size (default: 1MB)

## Debugging & Troubleshooting

### Enable Debug Mode
Set `LOG_LEVEL=DEBUG` in `.env` to enable detailed request/response logging.

### Common Issues

**"Service Unavailable" (500 Error)**
- Check `logs/error.log` for detailed error information
- Verify file permissions on `logs/` directory (need write access)
- Ensure PHP cURL extension is enabled

**"Authentication Failed" from Coda**
- Verify `CODA_BEARER_TOKEN` is correct and not expired
- Check `CODA_WEBHOOK_URL` format and accessibility
- Review logs for the exact error response from Coda

**Request Timeout Issues**
- Increase `REQUEST_TIMEOUT` value
- Check server outbound connectivity restrictions
- Verify Coda.io endpoint is responding

### Health Check Diagnostics
The `/webhook/health` endpoint provides:
- Service status and version
- Configuration validation
- Optional connectivity test to Coda.io
- Memory usage and performance metrics

## cPanel/Shared Hosting Specific

### File Structure in cPanel
```
public_html/webhook/     # Upload all files here
├── public/
│   ├── index.php       # Main entry point
│   └── .htaccess       # URL rewriting
├── src/                # Core PHP classes
├── config/             # Configuration
└── logs/               # Auto-created log directory
```

### URL Routing
- Main webhook: `https://domain.com/webhook/chip`
- Health check: `https://domain.com/webhook/health` 
- Info page: `https://domain.com/webhook/` (GET request)

### Permissions Setup
```bash
chmod 755 logs/         # Ensure logs directory is writable
chmod 644 config/.env   # Protect environment file
```

## Error Handling Strategy

1. **Input Validation Errors**: Return 400 Bad Request with details
2. **Authentication Errors**: Return 401/403 with generic message  
3. **Network Errors**: Retry up to 3 times with exponential backoff
4. **Server Errors**: Return 500 with generic message, log details
5. **Client Errors from Coda**: Return 502 Bad Gateway (no retry)

## Performance Characteristics

- **Typical Response Time**: < 2 seconds end-to-end
- **Memory Usage**: ~2-4MB per request (pure PHP, no frameworks)
- **Concurrent Requests**: Handled via web server (Apache/Nginx)
- **Log Rotation**: Automatic archival after 30 days (configurable)

## Development Notes

- **Zero Dependencies**: Pure PHP implementation for maximum cPanel compatibility
- **PHP Version**: Requires PHP 7.4+ with cURL extension
- **Error Reporting**: Disabled in production, logged to files
- **CORS Support**: Basic CORS headers for development/testing
