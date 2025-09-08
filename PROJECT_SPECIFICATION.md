# Chip-to-Coda Webhook Relay - Project Specification

## Project Overview

**Project Name:** Chip-to-Coda Webhook Relay  
**Purpose:** A middleware service that receives webhook notifications from Chip payment gateway and forwards them to Coda.io with proper authentication headers.  
**Hosting:** cPanel-compatible PHP application  

## Problem Statement

Chip payment gateway sends webhook notifications but does not support custom headers for authentication. Coda.io webhooks require a Bearer token in the Authorization header. This relay service bridges this gap by:

1. Receiving webhook payloads from Chip
2. Adding the required Bearer token authentication header
3. Forwarding the payload to Coda.io

## Requirements

### Functional Requirements

1. **Webhook Reception**
   - Accept HTTP POST requests from Chip payment gateway
   - Support standard webhook payload formats
   - Validate incoming requests (optional signature verification)

2. **Authentication Management**
   - Store and manage Coda.io Bearer token securely
   - Add Authorization header to outgoing requests

3. **Payload Forwarding**
   - Forward complete webhook payload to Coda.io
   - Maintain original payload structure and content
   - Handle different content types (JSON, form-data)

4. **Error Handling**
   - Retry mechanism for failed requests
   - Proper HTTP status code responses
   - Comprehensive error logging

5. **Security**
   - Input validation and sanitization
   - Optional webhook signature verification
   - Secure token storage

### Non-Functional Requirements

1. **Performance**
   - Low latency forwarding (< 2 seconds)
   - Handle concurrent webhook requests
   - Minimal resource usage for cPanel hosting

2. **Reliability**
   - 99%+ uptime
   - Graceful error handling
   - Request retry mechanism

3. **Maintainability**
   - Clean, documented code
   - Configuration-driven setup
   - Easy deployment process

## Technical Architecture

### Technology Stack
- **Language:** PHP 7.4+ (cPanel compatibility)
- **Dependencies:** cURL for HTTP requests, JSON handling
- **Storage:** File-based configuration (no database required)
- **Logging:** File-based logging system

### System Flow
```
Chip Gateway → Webhook Relay → Coda.io
```

1. Chip sends POST request to relay endpoint
2. Relay validates and processes the request
3. Relay adds Bearer token to headers
4. Relay forwards request to Coda.io
5. Relay returns response to Chip

### File Structure
```
/
├── public/
│   ├── index.php (main webhook endpoint)
│   └── .htaccess (URL rewriting)
├── config/
│   ├── config.php (configuration settings)
│   └── .env.example (environment template)
├── src/
│   ├── WebhookRelay.php (main relay class)
│   ├── Logger.php (logging functionality)
│   └── Validator.php (request validation)
├── logs/ (log files)
├── docs/ (documentation)
└── tests/ (unit tests)
```

## Configuration Requirements

### Environment Variables
- `CODA_BEARER_TOKEN`: Authentication token for Coda.io
- `CODA_WEBHOOK_URL`: Target Coda.io webhook endpoint
- `CHIP_WEBHOOK_SECRET`: Optional signature verification secret
- `LOG_LEVEL`: Logging verbosity (DEBUG, INFO, ERROR)

### Configuration Settings
- Request timeout values
- Retry attempts and intervals
- Allowed origins/IPs (optional)
- SSL/TLS verification settings

## Security Considerations

1. **Token Security**
   - Store Bearer token in environment variables or secure config
   - Never log authentication tokens
   - Use file permissions to protect config files

2. **Input Validation**
   - Validate all incoming webhook data
   - Sanitize headers and payload content
   - Check content-type and size limits

3. **Network Security**
   - Optional IP whitelisting for Chip gateway
   - HTTPS enforcement
   - Rate limiting considerations

4. **Webhook Signature Verification**
   - Optional: Verify Chip webhook signatures
   - Protect against replay attacks
   - Validate request timestamp

## Error Handling & Monitoring

### Error Types
1. **Network Errors**: Timeout, connection failures
2. **Authentication Errors**: Invalid Bearer token
3. **Validation Errors**: Invalid payload format
4. **Server Errors**: PHP errors, resource limits

### Logging Strategy
- Request/response logging for debugging
- Error logging with stack traces
- Performance metrics (response times)
- Log rotation to manage disk space

### Monitoring Points
- Webhook processing success rate
- Response times to Coda.io
- Error frequency and types
- Server resource usage

## Deployment Considerations

### cPanel Requirements
- PHP 7.4 or higher
- cURL extension enabled
- File write permissions for logs
- .htaccess support for URL rewriting

### Installation Steps
1. Upload files to cPanel file manager
2. Configure environment variables
3. Set proper file permissions
4. Test webhook endpoint
5. Configure Chip gateway URL

### Domain/URL Structure
- Main endpoint: `https://yourdomain.com/webhook/chip`
- Health check: `https://yourdomain.com/webhook/health`
- Logs access: `https://yourdomain.com/webhook/logs` (admin only)

## Testing Strategy

### Test Types
1. **Unit Tests**: Individual component testing
2. **Integration Tests**: End-to-end workflow testing
3. **Load Tests**: Concurrent request handling
4. **Security Tests**: Input validation and injection attempts

### Test Scenarios
- Valid webhook from Chip → successful forward to Coda
- Invalid authentication token → proper error handling
- Network timeout → retry mechanism activation
- Malformed payload → validation error response
- Concurrent requests → proper handling without conflicts

## Success Metrics

1. **Functionality**: 100% successful webhook forwarding for valid requests
2. **Performance**: < 2 second end-to-end processing time
3. **Reliability**: < 0.1% error rate under normal conditions
4. **Security**: Zero security incidents or token exposures

## Future Enhancements

1. **Admin Dashboard**: Web interface for monitoring and configuration
2. **Multiple Integrations**: Support for other payment gateways
3. **Database Storage**: Move from file-based to database configuration
4. **Advanced Monitoring**: Integration with monitoring services
5. **Webhook Transformation**: Ability to modify payload structure

## Risk Assessment

### High Risk
- Bearer token exposure in logs or configuration
- Service downtime during payment processing

### Medium Risk
- cPanel resource limits affecting performance
- PHP version compatibility issues

### Low Risk
- Minor configuration errors
- Log file disk space usage

## Conclusion

This webhook relay service provides a simple, secure, and reliable solution for bridging the authentication gap between Chip payment gateway and Coda.io. The PHP-based implementation ensures compatibility with cPanel hosting while maintaining security and performance standards.
