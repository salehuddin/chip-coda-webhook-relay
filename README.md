# Chip-to-Coda Webhook Relay

A lightweight PHP middleware service that receives webhook notifications from Chip payment gateway and forwards them to Coda.io with proper Bearer token authentication.

## Problem Solved

Chip payment gateway doesn't support custom headers in webhook requests, but Coda.io requires a Bearer token in the Authorization header. This relay service bridges that gap by:

- Receiving webhook payloads from Chip
- Adding the required Bearer token authentication header  
- Forwarding the complete payload to Coda.io

## Features

- ✅ **Zero Dependencies**: Pure PHP implementation, no external libraries
- ✅ **cPanel Compatible**: Designed for shared hosting environments
- ✅ **Secure**: Environment-based token management with input validation
- ✅ **Reliable**: Built-in retry mechanism and comprehensive error handling
- ✅ **Lightweight**: Minimal resource usage and fast response times
- ✅ **Monitoring**: Comprehensive logging and health check endpoint

## Quick Start

### 1. Upload Files

Upload all files to your cPanel hosting account in a directory like `webhook/` or `relay/`.

### 2. Configure Environment

Copy and customize the configuration:
```bash
cp config/.env.example config/.env
```

Edit `config/.env` with your settings:
```env
CODA_BEARER_TOKEN=your_coda_bearer_token_here
CODA_WEBHOOK_URL=https://coda.io/hooks/your-webhook-id
CHIP_WEBHOOK_SECRET=optional_signature_verification_secret
LOG_LEVEL=INFO
```

### 3. Set Permissions

Ensure the `logs/` directory is writable:
```bash
chmod 755 logs/
```

### 4. Test the Endpoint

Visit your health check URL:
```
https://yourdomain.com/webhook/health
```

You should see a JSON response confirming the service is running.

### 5. Configure Chip Gateway

In your Chip payment gateway settings, set the webhook URL to:
```
https://yourdomain.com/webhook/chip
```

## File Structure

```
/
├── public/
│   ├── index.php          # Main webhook endpoint
│   └── .htaccess         # URL rewriting and security
├── src/
│   ├── WebhookRelay.php  # Core relay functionality
│   ├── Logger.php        # Logging system
│   └── Validator.php     # Request validation
├── config/
│   ├── config.php        # Configuration loader
│   └── .env.example      # Environment template
├── logs/                 # Log files (auto-created)
├── docs/                 # Documentation
├── tests/               # Unit tests
└── README.md            # This file
```

## API Endpoints

### Main Webhook Endpoint
```
POST /webhook/chip
Content-Type: application/json (or application/x-www-form-urlencoded)

Receives webhook from Chip and forwards to Coda.io
```

### Health Check
```
GET /webhook/health

Returns service status and configuration info
```

## Configuration

### Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `CODA_BEARER_TOKEN` | Authentication token for Coda.io | Yes |
| `CODA_WEBHOOK_URL` | Target Coda.io webhook endpoint | Yes |
| `CHIP_WEBHOOK_SECRET` | Optional signature verification secret | No |
| `LOG_LEVEL` | Logging verbosity (DEBUG, INFO, ERROR) | No (default: INFO) |

### Advanced Settings

Edit `config/config.php` for additional settings:
- Request timeout values
- Retry attempts and intervals
- SSL verification settings
- IP whitelist (if needed)

## Security

### Token Protection
- Store tokens in environment variables only
- Never commit tokens to version control
- Use file permissions to protect config files

### Request Validation
- All input is validated and sanitized
- Optional webhook signature verification
- IP whitelist support for enhanced security

### SSL/HTTPS
- Always use HTTPS in production
- SSL certificate verification enabled by default

## Logging

Logs are written to the `logs/` directory:
- `webhook.log`: General application logs
- `error.log`: Error-specific logs
- Log rotation prevents disk space issues

### Log Levels
- **DEBUG**: Detailed request/response info
- **INFO**: General operational messages
- **ERROR**: Error conditions only

## Troubleshooting

### Common Issues

1. **"Service Unavailable" Error**
   - Check file permissions on `logs/` directory
   - Verify PHP version is 7.4 or higher

2. **"Authentication Failed" Error**
   - Verify `CODA_BEARER_TOKEN` is correct
   - Check Coda.io webhook URL format

3. **"Connection Timeout" Error**
   - Check server's outbound connection restrictions
   - Verify Coda.io URL is accessible from your server

### Debug Mode

Enable debug logging by setting `LOG_LEVEL=DEBUG` in your `.env` file. This will log all request/response details for troubleshooting.

### Health Check

Use the health check endpoint to verify:
- Service is running
- Configuration is loaded
- Network connectivity to Coda.io

## Deployment

### cPanel Requirements
- PHP 7.4 or higher
- cURL extension enabled
- mod_rewrite support (for clean URLs)
- File write permissions

### Production Checklist
- [ ] Upload all files to production
- [ ] Configure environment variables
- [ ] Set proper file permissions
- [ ] Test health check endpoint
- [ ] Configure Chip webhook URL
- [ ] Monitor logs for initial requests
- [ ] Set up log rotation if needed

## Support

For issues and questions:
1. Check the logs in the `logs/` directory
2. Review the health check endpoint response
3. Verify configuration settings
4. Consult the troubleshooting section above

## License

MIT License - see LICENSE file for details.
