# Tests Directory

This directory contains diagnostic and testing tools for the Chip-to-Coda Webhook Relay.

## Available Tests

### `test-coda-connection.php`

Tests connectivity between the server and Coda.io to ensure webhooks can be delivered successfully.

**Purpose:**

- Verify cURL is working properly
- Test SSL certificate verification
- Confirm API token is valid
- Check network connectivity to Coda.io

**Usage:**

```bash
# Run from command line
php test-coda-connection.php

# Or via web browser (if accessible)
https://yourdomain.com/tests/test-coda-connection.php
```

**What it checks:**

- ✅ PHP version and cURL extension
- ✅ CA bundle file exists
- ✅ SSL/TLS connection to Coda.io
- ✅ API authentication
- ✅ Network latency and connection time

**Expected output:**

```
=== Coda Connectivity Test ===

URL: https://coda.io/apis/v1/docs/.../hooks/automation/...
Token: 4f6fb7ad-6848-40bf-9...
CA Bundle: /path/to/cacert.pem
CA Bundle exists: YES

Sending test request...

=== RESULTS ===
Duration: 536.52 ms
HTTP Code: 202
cURL Error: None

✅ SUCCESS!
🎉 Connection test PASSED!
```

**Troubleshooting:**

If the test fails, check:

1. **PHP Version:** Must be 8.0 or higher

   ```bash
   php -v
   ```

2. **cURL Extension:** Must be enabled

   ```bash
   php -m | grep curl
   ```

3. **CA Bundle:** Must exist in `../src/cacert.pem`

   - Download from: https://curl.se/ca/cacert.pem
   - Save to: `src/cacert.pem`

4. **API Token:** Must be valid and not expired

   - Check in your config file
   - Verify in Coda.io settings

5. **Network:** Server must allow outbound HTTPS
   - Check firewall rules
   - Verify DNS resolution: `nslookup coda.io`

## When to Run Tests

**Before deployment:**

- ✅ After setting up on a new server
- ✅ After PHP version upgrade
- ✅ After server configuration changes

**During troubleshooting:**

- ✅ When webhooks stop working
- ✅ After SSL certificate updates
- ✅ When seeing connection errors in logs

**Periodic checks:**

- ✅ Monthly health check
- ✅ After cPanel/server updates

## Test Results

Test results are saved to `test-result.log` in this directory for later review.

## Security Note

⚠️ **Important:** This test script uses your production API token.

- Do NOT expose this directory publicly
- Add to `.htaccess` to restrict access:

  ```apache
  Order Deny,Allow
  Deny from all
  Allow from 127.0.0.1
  ```

- Or use IP whitelist in your web server config

## Adding More Tests

To add new tests:

1. Create a new PHP file in this directory
2. Follow the naming convention: `test-{feature}.php`
3. Update this README with usage instructions
4. Include clear success/failure output

## Support

If tests fail and you can't resolve the issue:

1. Check the verbose cURL log in the output
2. Review `test-result.log` for details
3. Check the main webhook logs
4. Refer to the troubleshooting documentation

---

**Last Updated:** January 11, 2026  
**Maintainer:** Webhook Relay Team
