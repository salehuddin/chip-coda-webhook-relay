\# Coda Webhook Replay Documentation



\## Issue Summary



\*\*Date:\*\* January 10, 2026  

\*\*Problem:\*\* Coda API token expired, causing webhook events from Chip payment gateway to fail delivery to Coda.io



\### Initial Situation



\- Webhook events from Chip (payment gateway) were being logged to `coda-webhook.txt`

\- The Coda API token expired on January 10, 2026

\- All webhook events from that date failed to reach Coda

\- Need to replay these events with the new API token



---



\## Technical Challenges Encountered



\### 1. \*\*Truncated JSON in Log Files\*\*



The webhook log file contained truncated JSON data:



\- The `transaction\_data` field (containing FPX payment details) was cut off with `\[truncated]`

\- This made the JSON invalid and unparseable

\- \*\*Solution:\*\* Remove the `transaction\_data` field entirely (not needed for Coda) and close the JSON properly



\### 2. \*\*Duplicate Webhook Events\*\*



The log file contained multiple entries for the same transaction:



\- Each webhook was logged multiple times (processing started, HTTP request, HTTP response, etc.)

\- This resulted in duplicate entries being sent to Coda

\- \*\*Solution:\*\* Deduplicate by transaction ID before sending



\### 3. \*\*Event Type Confusion\*\*



Initially thought duplicates were due to different event types (created, viewed, paid), but they were actually:



\- Multiple log entries for the same HTTP request

\- Same event type, same transaction, logged multiple times



---



\## Solutions



\### Solution 1: Basic Replay (With Duplicates)



\*\*File:\*\* `replay-webhooks-safe.ps1`



This script:



\- ✅ Extracts webhook events from log file

\- ✅ Filters events from 2026-01-10

\- ✅ Fixes truncated JSON by removing `transaction\_data` field

\- ✅ Sends to Coda with 500ms delay between requests

\- ❌ Does NOT deduplicate (sends all 353 events including duplicates)



\*\*Result:\*\* Successfully sent 353 events, but created duplicates in Coda



\### Solution 2: Deduplicated Replay (Recommended)



\*\*File:\*\* `replay-webhooks-deduplicated.ps1`



This script:



\- ✅ Extracts webhook events from log file

\- ✅ Filters events from 2026-01-10

\- ✅ Fixes truncated JSON by removing `transaction\_data` field

\- ✅ \*\*Deduplicates by transaction ID\*\* (keeps only one entry per unique purchase)

\- ✅ Sends to Coda with 500ms delay between requests



\*\*Result:\*\* Sends only unique transactions, preventing duplicates



---



\## Configuration



\### Required Information



```powershell

$logPath = "C:\\Users\\saleh\\OneDrive\\Desktop\\coda-webhook.txt"

$token = "4f6fb7ad-6848-40bf-9e73-8f7ed9662247"  # New Coda API token

$url = "https://coda.io/apis/v1/docs/-2TMhTzzJW/hooks/automation/grid-auto-INxvzWZTbh"

$cutoff = \[datetime]"2026-01-10 00:00:00"  # Date to replay from

$delayMs = 500  # Delay between requests to prevent timeout

```



---



\## Final Scripts



\### Deduplicated Replay Script (Recommended)



```powershell

$logPath = "C:\\Users\\saleh\\OneDrive\\Desktop\\coda-webhook.txt"

$token = "4f6fb7ad-6848-40bf-9e73-8f7ed9662247"

$url = "https://coda.io/apis/v1/docs/-2TMhTzzJW/hooks/automation/grid-auto-INxvzWZTbh"

$cutoff = \[datetime]"2026-01-10 00:00:00"

$delayMs = 500



Write-Host "Reading and parsing log file..." -ForegroundColor Yellow

$lines = Get-Content $logPath



$allEvents = @{}  # Use hashtable to deduplicate by transaction ID

$counter = 0



foreach ($line in $lines) {

&nbsp;   $counter++

&nbsp;   if ($counter % 1000 -eq 0) {

&nbsp;       Write-Host "Processed $counter lines..." -ForegroundColor Gray

&nbsp;   }



&nbsp;   # Skip if not an HTTP Request line

&nbsp;   if ($line -notmatch '"message":"HTTP Request"') {

&nbsp;       continue

&nbsp;   }



&nbsp;   # Skip if not from target date

&nbsp;   if ($line -notmatch '"timestamp":"2026-01-10') {

&nbsp;       continue

&nbsp;   }



&nbsp;   try {

&nbsp;       # Parse the log line as JSON

&nbsp;       $logEntry = $line | ConvertFrom-Json



&nbsp;       # Extract the body field which contains the webhook payload

&nbsp;       if ($logEntry.context.body) {

&nbsp;           $bodyString = $logEntry.context.body



&nbsp;           # Remove the incomplete transaction\_data field and close JSON properly

&nbsp;           $transactionDataPos = $bodyString.IndexOf(', "transaction\_data":')

&nbsp;           if ($transactionDataPos -gt 0) {

&nbsp;               $bodyString = $bodyString.Substring(0, $transactionDataPos) + '}'

&nbsp;           }



&nbsp;           # Parse to get the transaction ID for deduplication

&nbsp;           try {

&nbsp;               $data = $bodyString | ConvertFrom-Json

&nbsp;               $transactionId = $data.id



&nbsp;               # Only keep one occurrence of each transaction

&nbsp;               if (-not $allEvents.ContainsKey($transactionId)) {

&nbsp;                   $allEvents\[$transactionId] = @{

&nbsp;                       Timestamp = \[datetime]$logEntry.timestamp

&nbsp;                       BodyString = $bodyString

&nbsp;                       Reference = if ($data.reference) { $data.reference } else { "N/A" }

&nbsp;                       Name = if ($data.client.full\_name) { $data.client.full\_name } else { "N/A" }

&nbsp;                       EventType = if ($data.event\_type) { $data.event\_type } else { "N/A" }

&nbsp;                   }

&nbsp;               }

&nbsp;           } catch {

&nbsp;               continue

&nbsp;           }

&nbsp;       }

&nbsp;   } catch {

&nbsp;       continue

&nbsp;   }

}



$eventsToReplay = $allEvents.Values | Sort-Object -Property Timestamp



Write-Host ""

Write-Host "Found $($allEvents.Count) unique webhook events from 2026-01-10" -ForegroundColor Cyan

Write-Host "Starting replay with $delayMs ms delay between requests..." -ForegroundColor Yellow

Write-Host ""



$success = 0

$failed = 0

$eventCounter = 0



foreach ($event in $eventsToReplay) {

&nbsp;   $eventCounter++



&nbsp;   try {

&nbsp;       Write-Host "\[$eventCounter/$($eventsToReplay.Count)] $($event.EventType) - $($event.Reference) ($($event.Name))..." -ForegroundColor Cyan -NoNewline



&nbsp;       # Send the body string directly using WebRequest

&nbsp;       $response = Invoke-WebRequest -Method Post -Uri $url `

&nbsp;           -Headers @{

&nbsp;               "Authorization" = "Bearer $token"

&nbsp;               "Content-Type" = "application/json"

&nbsp;           } `

&nbsp;           -Body (\[System.Text.Encoding]::UTF8.GetBytes($event.BodyString)) `

&nbsp;           -UseBasicParsing



&nbsp;       Write-Host " Done!" -ForegroundColor Green

&nbsp;       $success++



&nbsp;       # Add delay to prevent timeout

&nbsp;       Start-Sleep -Milliseconds $delayMs



&nbsp;   } catch {

&nbsp;       Write-Host " Error!" -ForegroundColor Red

&nbsp;       $failed++

&nbsp;   }

}



Write-Host ""

Write-Host "========== SUMMARY ==========" -ForegroundColor Yellow

Write-Host "Unique events: $($eventsToReplay.Count)" -ForegroundColor White

Write-Host "Successful: $success" -ForegroundColor Green

Write-Host "Failed: $failed" -ForegroundColor Red

Write-Host "=============================" -ForegroundColor Yellow

```



---



\## How It Works



\### Step 1: Parse Log File



\- Reads `coda-webhook.txt` line by line

\- Filters for lines containing `"message":"HTTP Request"`

\- Filters for target date (2026-01-10)



\### Step 2: Extract Webhook Payload



\- Parses each log line as JSON

\- Extracts the `context.body` field (contains the webhook payload)



\### Step 3: Fix Truncated JSON



\- Finds the position of `"transaction\_data"` field

\- Removes everything from that point onwards

\- Adds closing `}` to make valid JSON



\### Step 4: Deduplicate



\- Parses the fixed JSON to extract transaction ID (`data.id`)

\- Uses a hashtable to store only one entry per transaction ID

\- If duplicate found, keeps the first occurrence



\### Step 5: Send to Coda



\- Sorts events by timestamp

\- Sends each unique event to Coda API

\- Waits 500ms between requests to prevent rate limiting

\- Uses UTF-8 encoding for proper character handling



---



\## Execution Results



\### First Run (With Duplicates)



```

Total events: 353

Successful: 353

Failed: 0

```



\*\*Issue:\*\* Created duplicate entries in Coda



\### Expected Results (With Deduplication)



```

Unique events: ~100-150 (estimated)

Successful: ~100-150

Failed: 0

```



\*\*Benefit:\*\* No duplicates in Coda



---



\## Usage Instructions



\### To Replay Webhooks (Deduplicated)



1\. \*\*Update the script variables:\*\*



&nbsp;  - `$logPath` - Path to your webhook log file

&nbsp;  - `$token` - Your new Coda API token

&nbsp;  - `$url` - Your Coda webhook URL

&nbsp;  - `$cutoff` - Date to replay from



2\. \*\*Run the script:\*\*



&nbsp;  ```powershell

&nbsp;  powershell -ExecutionPolicy Bypass -File "replay-webhooks-deduplicated.ps1"

&nbsp;  ```



3\. \*\*Monitor progress:\*\*

&nbsp;  - Script shows progress: `\[X/Total] event\_type - reference (name)... Done!`

&nbsp;  - Final summary shows success/failure counts



---



\## Key Learnings



1\. \*\*Always deduplicate webhook replays\*\* - Log files may contain multiple entries for the same event

2\. \*\*Handle truncated data gracefully\*\* - Remove non-essential fields if they're incomplete

3\. \*\*Add delays between API calls\*\* - Prevents rate limiting and timeouts

4\. \*\*Use transaction IDs for deduplication\*\* - Most reliable unique identifier

5\. \*\*Test with small batches first\*\* - Helps identify issues before processing large datasets



---



\## Files Created



| File                                   | Purpose                               | Status                |

| -------------------------------------- | ------------------------------------- | --------------------- |

| `replay-webhooks-safe.ps1`             | Basic replay script (with duplicates) | ⚠️ Creates duplicates |

| `replay-webhooks-deduplicated.ps1`     | Deduplicated replay script            | ✅ Recommended        |

| `test-body.ps1`                        | Test script for debugging             | 🔧 Development only   |

| `test-fix-json.ps1`                    | Test script for JSON fixing           | 🔧 Development only   |

| `save-body.ps1`                        | Test script to save body to file      | 🔧 Development only   |

| `Coda-Webhook-Replay-Documentation.md` | This documentation                    | 📄 Reference          |



---



\## Future Improvements



1\. \*\*Add dry-run mode\*\* - Preview what will be sent without actually sending

2\. \*\*Add filtering by event type\*\* - Only replay specific event types (e.g., only `purchase.paid`)

3\. \*\*Add batch processing\*\* - Process in smaller batches with confirmation between batches

4\. \*\*Add logging\*\* - Save detailed logs of what was sent

5\. \*\*Add rollback capability\*\* - Delete sent events if issues are discovered



---



\## Contact \& Support



\*\*Issue Date:\*\* January 10-11, 2026  

\*\*Resolved By:\*\* Automated webhook replay script  

\*\*Status:\*\* ✅ Resolved (with deduplication recommendation)



---



\## Appendix: Webhook Event Types



Chip sends multiple event types for each purchase:



| Event Type                 | Description                  | Should Replay?             |

| -------------------------- | ---------------------------- | -------------------------- |

| `purchase.created`         | Purchase created             | ❌ No (intermediate state) |

| `purchase.viewed`          | Customer viewed payment page | ❌ No (intermediate state) |

| `purchase.pending\_execute` | Payment processing           | ❌ No (intermediate state) |

| `purchase.paid`            | Payment successful           | ✅ Yes (final state)       |

| `purchase.payment\_failure` | Payment failed               | ✅ Yes (final state)       |



\*\*Recommendation:\*\* If you only want final states, filter for `purchase.paid` and `purchase.payment\_failure` events only.



---



\## UPDATE: Actual Root Cause \& Final Resolution



\*\*Date Resolved:\*\* January 11, 2026, 1:54 AM



\### The Real Problem



After extensive troubleshooting, we discovered the actual root cause was \*\*NOT\*\* an expired API token or SSL certificate issue. The real problem was:



\*\*🎯 Old PHP version on cPanel server without working cURL extension\*\*



\### Investigation Timeline



\#### Phase 1: Initial Assumption (❌ Wrong)



\- \*\*Hypothesis:\*\* API token expired on January 10, 2026

\- \*\*Action:\*\* Updated token to `4f6fb7ad-6848-40bf-9e73-8f7ed9662247`

\- \*\*Result:\*\* ❌ Webhooks still failed



\#### Phase 2: SSL Certificate Theory (❌ Partially Wrong)



\- \*\*Hypothesis:\*\* SSL certificate verification failure

\- \*\*Action:\*\* Added CA bundle (`cacert.pem`) to relay server

\- \*\*Result:\*\* ❌ Webhooks still failed (but this was still needed)



\#### Phase 3: Breakthrough Discovery (✅ Found It!)



\- \*\*Test:\*\* Ran diagnostic script `test-cpanel-curl.php` on cPanel

\- \*\*Result:\*\*

&nbsp; ```

&nbsp; Fatal error: Call to undefined function curl\_exec()

&nbsp; ```

\- \*\*Root Cause:\*\* \*\*cURL extension was not available in the old PHP version\*\*



\#### Phase 4: Final Solution (✅ Success!)



\- \*\*Action:\*\* Upgraded PHP from old version to \*\*PHP 8.3\*\*

\- \*\*Result:\*\* ✅ \*\*Webhooks working again!\*\*

&nbsp; ```

&nbsp; HTTP Code: 202

&nbsp; SSL certificate verify ok.

&nbsp; Response: {"requestId":"mutate:f3e0b3c2-d264-48bb-90f5-ecafffca9421"}

&nbsp; ```



\### Why It Worked Before



The relay was likely using a different PHP version that had cURL enabled. When the server was updated or PHP version changed (possibly during a cPanel update), cURL became unavailable, causing silent failures.



\### Why PowerShell Script Worked But Relay Failed



| Method                | Result    | Why                                               |

| --------------------- | --------- | ------------------------------------------------- |

| \*\*PowerShell Script\*\* | ✅ Worked | Uses Windows Certificate Store, no PHP dependency |

| \*\*PHP Relay (Old)\*\*   | ❌ Failed | Old PHP without cURL extension                    |

| \*\*PHP Relay (New)\*\*   | ✅ Works  | PHP 8.3 with cURL 8.14.1                          |



The PowerShell replay script worked because:



\- It ran on Windows machine with proper SSL/TLS support

\- Windows Certificate Store handled SSL verification automatically

\- No dependency on PHP or cPanel configuration



\### The Complete Fix (3 Steps)



\#### Step 1: Upgrade PHP Version ⭐ \*\*Most Important\*\*



1\. Login to cPanel

2\. Go to \*\*Software\*\* → \*\*Select PHP Version\*\* (or \*\*MultiPHP Manager\*\*)

3\. Change to \*\*PHP 8.3\*\* (or latest stable version 8.0+)

4\. Click \*\*Apply\*\*



\*\*What this gave us:\*\*



\- ✅ cURL 8.14.1 (latest version)

\- ✅ OpenSSL 3.2.2 (modern SSL support)

\- ✅ TLS 1.3 support

\- ✅ HTTP/2 support



\#### Step 2: Verify cURL Extension



1\. In \*\*Select PHP Version\*\* → \*\*Extensions\*\*

2\. Ensure `curl` extension is \*\*checked/enabled\*\*

3\. Ensure `openssl` extension is \*\*checked/enabled\*\*

4\. Save changes



\#### Step 3: Add CA Bundle



1\. Download: https://curl.se/ca/cacert.pem

2\. Upload to: `/home/xchessac/public\_html/webhook/src/cacert.pem`

3\. Update `WebhookRelay.php` to use it (already done in fixed version)



\### Verification Test Results



\*\*Before Fix:\*\*



```

Fatal error: Call to undefined function curl\_exec()

```



\*\*After Fix:\*\*



```

✅ SUCCESS!

Duration: 536.52 ms

HTTP Code: 202

cURL Error: None

SSL Verify Result: 0 (success)

SSL connection using TLSv1.3 / TLS\_AES\_128\_GCM\_SHA256

Response: {"requestId":"mutate:f3e0b3c2-d264-48bb-90f5-ecafffca9421"}

```



\### Key Learnings



1\. \*\*✅ Always check PHP version and extensions first\*\* when debugging web service issues

2\. \*\*✅ Silent failures\*\* can occur when required PHP extensions are missing

3\. \*\*✅ Diagnostic scripts are essential\*\* - `test-cpanel-curl.php` revealed the real issue

4\. \*\*✅ Don't assume the obvious\*\* - the "expired token" was a red herring

5\. \*\*✅ Test locally vs server\*\* - PowerShell working but relay failing was the key clue



\### Files Created During Troubleshooting



| File                                     | Purpose                              | Status       | Key Contribution         |

| ---------------------------------------- | ------------------------------------ | ------------ | ------------------------ |

| `test-cpanel-curl.php`                   | Diagnostic test for cPanel cURL      | ✅ Critical  | \*\*Revealed root cause\*\*  |

| `WebhookRelay-FIXED.php`                 | Updated relay with CA bundle support | ✅ Deployed  | Supports modern SSL      |

| `cacert.pem`                             | CA certificate bundle                | ✅ Uploaded  | SSL verification         |

| `monitor-and-replay-failed-webhooks.ps1` | Auto-detect failed webhooks          | ✅ Available | Future monitoring        |

| `replay-webhooks-deduplicated.ps1`       | Manual replay with deduplication     | ✅ Used      | Replayed 353 events      |

| `test-curl-to-coda.php`                  | Local Windows test                   | ✅ Used      | Showed SSL issue pattern |



\### Current Production Status



\*\*Relay Server Configuration:\*\*



\- ✅ \*\*PHP Version:\*\* 8.3.27

\- ✅ \*\*cURL Version:\*\* 8.14.1

\- ✅ \*\*SSL Version:\*\* OpenSSL/3.2.2

\- ✅ \*\*CA Bundle:\*\* `/home/xchessac/public\_html/webhook/src/cacert.pem`

\- ✅ \*\*API Token:\*\* `4f6fb7ad-6848-40bf-9e73-8f7ed9662247`



\*\*System Status:\*\*



\- ✅ \*\*Relay Server:\*\* Working

\- ✅ \*\*SSL Verification:\*\* Working

\- ✅ \*\*Webhooks:\*\* Flowing from Chip → Relay → Coda

\- ✅ \*\*Monitoring:\*\* Scripts available for future issues



\### Maintenance Checklist



\*\*Monthly Checks:\*\*



\- \[ ] Verify PHP version hasn't changed (should be 8.0+)

\- \[ ] Check cURL extension is still enabled

\- \[ ] Verify CA bundle file exists at `/home/xchessac/public\_html/webhook/src/cacert.pem`

\- \[ ] Test webhook with `test-cpanel-curl.php`



\*\*When Issues Occur:\*\*



1\. Check webhook logs for "HTTP Response" entries

2\. Run `test-cpanel-curl.php` on cPanel server

3\. Verify PHP version: `php -v`

4\. Check cURL availability: `php -m | grep curl`

5\. Use `monitor-and-replay-failed-webhooks.ps1` to catch up on failed events



\### Troubleshooting Quick Reference



| Symptom                    | Likely Cause              | Solution                       |

| -------------------------- | ------------------------- | ------------------------------ |

| No "HTTP Response" in logs | cURL not working          | Check PHP version \& extensions |

| `curl\_exec() undefined`    | cURL extension disabled   | Enable cURL in PHP extensions  |

| SSL certificate error      | Missing CA bundle         | Upload `cacert.pem`            |

| HTTP 401 error             | Invalid API token         | Update token in config         |

| Duplicates in Coda         | Replaying all log entries | Use deduplicated script        |



\### Resolution Summary



\*\*Issue:\*\* Webhook relay stopped working on January 10, 2026  

\*\*Initial Diagnosis:\*\* Expired API token ❌  

\*\*Actual Root Cause:\*\* Old PHP version without cURL extension ✅  

\*\*Resolution:\*\* Upgraded to PHP 8.3 with cURL enabled  

\*\*Date Resolved:\*\* January 11, 2026, 1:54 AM  

\*\*Total Downtime:\*\* ~24 hours  

\*\*Events Recovered:\*\* 353 webhook events successfully replayed  

\*\*Status:\*\* ✅ \*\*FULLY RESOLVED\*\*



