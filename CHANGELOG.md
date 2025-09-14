# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-01-14

### Added
- Initial release of Chip-to-Coda Webhook Relay
- Core webhook relay functionality from Chip to Coda.io
- Bearer token authentication for Coda.io webhooks
- Comprehensive input validation and security features
- IP whitelisting with CIDR range support
- Dual signature verification (HMAC and RSA/ECDSA)
- Retry mechanism with exponential backoff
- Comprehensive logging system with multiple levels
- Health check endpoint with connectivity testing
- cPanel/shared hosting compatibility
- Zero-dependency pure PHP implementation
- Complete documentation (README, PROJECT_SPECIFICATION, WARP.md)
- Production deployment ready

### Security
- Input sanitization and validation
- Token protection (never logged or exposed)
- SSL certificate verification
- Optional webhook signature verification
- File permission-based config protection

### Documentation
- Complete README with setup instructions
- Technical specification document
- Development guidelines (WARP.md)
- Environment configuration examples
- Troubleshooting guide

## [Unreleased]

### Planned
- Unit tests implementation
- Admin dashboard for monitoring
- Support for additional payment gateways
- Database configuration storage option
- Advanced monitoring integrations
