# Coda Automation Setup Checklist

## Pre-Setup Requirements
- [ ] Your webhook relay is working and forwarding data to Coda
- [ ] You have admin access to your Coda document
- [ ] You have a sample Chip webhook JSON for testing

## Step 1: Create Tables
### Create "Payment Records" Table
- [ ] Create new table named "Payment Records"
- [ ] Add all required columns:
  - [ ] Purchase ID (Text)
  - [ ] Status (Select List: paid, cancelled, failed)
  - [ ] Event Type (Text)
  - [ ] Amount (RM) (Currency)
  - [ ] Currency (Text)
  - [ ] Fee Amount (RM) (Currency)
  - [ ] Net Amount (RM) (Currency)
  - [ ] Payment Method (Text)
  - [ ] Transaction ID (Text)
  - [ ] Customer Email (Email)
  - [ ] Customer Name (Text)
  - [ ] Product Name (Text)
  - [ ] Payment Date (DateTime)
  - [ ] Is Test (Checkbox)
  - [ ] FPX Transaction ID (Text)
  - [ ] Bank Name (Text)
  - [ ] Raw JSON (Text)
  - [ ] Created At (DateTime)

### Create "Webhook Triggers" Table
- [ ] Create new table named "Webhook Triggers"
- [ ] Add required columns:
  - [ ] Webhook ID (Text)
  - [ ] Received At (DateTime)
  - [ ] Raw Data (Text)
  - [ ] Event Type (Text)
  - [ ] Processed (Checkbox)
  - [ ] Processing Notes (Text)

## Step 2: Configure Webhook
- [ ] Go to Document Settings → Webhooks
- [ ] Create new webhook pointing to "Webhook Triggers" table
- [ ] Copy webhook URL
- [ ] Update your relay's `CODA_WEBHOOK_URL` with new URL
- [ ] Test webhook reception with a sample payload

## Step 3: Create Automation Rules

### Main Processing Automation
- [ ] Create automation: "Process Payment Webhooks"
- [ ] Trigger: When rows are added to "Webhook Triggers"
- [ ] Condition: Raw Data contains payment events
- [ ] Action: Copy the main processing formula
- [ ] Test with sample data
- [ ] Verify payment record is created correctly

### Error Handling Automation
- [ ] Create automation: "Handle Processing Errors"
- [ ] Trigger: When rows are modified in "Webhook Triggers"
- [ ] Condition: Processed is false AND older than 5 minutes
- [ ] Action: Mark as failed with error message

## Step 4: Set Up Monitoring

### Create Views
- [ ] Recent Payments view (last 24 hours)
- [ ] Failed Payments view (status = failed)
- [ ] Test Payments view (is_test = true)
- [ ] Payment Summary view (grouped by status)

### Email Notifications
- [ ] Failed payment alert automation
- [ ] High-value payment notification
- [ ] Daily summary email (optional)

## Step 5: Testing

### Test Data Validation
- [ ] Paste sample JSON into Webhook Triggers
- [ ] Verify automation triggers
- [ ] Check payment record is created
- [ ] Verify all fields are populated correctly
- [ ] Confirm amounts converted from cents to RM
- [ ] Test duplicate prevention (paste same JSON twice)

### Test Different Event Types
- [ ] Test with `purchase.paid` event
- [ ] Test with `purchase.cancelled` event  
- [ ] Test with `purchase.failed` event
- [ ] Test with non-payment event (should be skipped)

## Step 6: Production Verification

### End-to-End Test
- [ ] Make a test purchase through Chip
- [ ] Verify webhook reaches your relay
- [ ] Confirm webhook forwarded to Coda
- [ ] Check payment record created automatically
- [ ] Validate all data is accurate

### Performance Check
- [ ] Multiple webhooks processed correctly
- [ ] No duplicate records created
- [ ] Processing completed within reasonable time
- [ ] Error handling works for malformed data

## Step 7: Documentation & Maintenance

### Team Access
- [ ] Document the process for your team
- [ ] Set up access permissions for relevant users
- [ ] Create dashboard for monitoring payment status

### Regular Maintenance
- [ ] Set up webhook cleanup automation (delete old processed webhooks)
- [ ] Schedule regular data validation checks
- [ ] Monitor processing success rates

## Common Issues Checklist

If automation isn't working:
- [ ] Check webhook URL is correct in relay configuration
- [ ] Verify Coda webhook is receiving data (check Webhook Triggers table)
- [ ] Confirm automation rules are enabled
- [ ] Check automation conditions match your event types
- [ ] Validate JSON structure matches your formulas
- [ ] Test with simple sample data first

If payments aren't being created:
- [ ] Check Raw Data contains valid JSON
- [ ] Verify event_type filtering condition
- [ ] Confirm table and column names match exactly
- [ ] Check for JSON parsing errors in Processing Notes
- [ ] Test formula components individually

If amounts are wrong:
- [ ] Verify division by 100 for cent conversion
- [ ] Check currency field extraction
- [ ] Validate fee and net amount calculations

## Success Criteria

Your automation is working correctly when:
- [ ] ✅ Webhooks arrive in Webhook Triggers table
- [ ] ✅ Payment events are automatically processed
- [ ] ✅ Payment records are created with correct data
- [ ] ✅ Amounts are properly converted to RM
- [ ] ✅ No duplicate records are created
- [ ] ✅ Non-payment events are skipped
- [ ] ✅ Error handling works for bad data
- [ ] ✅ Email notifications work for alerts

## Final Test

Send yourself a test purchase for RM 5.00 and verify:
1. Chip webhook is sent to your relay
2. Relay forwards to Coda webhook  
3. Coda automation processes the webhook
4. Payment record is created with Amount (RM) = 5.00
5. All customer and product details are captured
6. Status is set to "paid"
7. No errors in Processing Notes

## Support Resources

- **Coda Formula Reference**: https://coda.io/formulas
- **JSON Parsing in Coda**: Use ParseJSON() function
- **Webhook Troubleshooting**: Check webhook logs in Coda settings
- **Your Webhook Relay Logs**: Check logs/ directory for relay issues

---

**Completion Date**: ___________
**Tested By**: ___________
**Production Ready**: [ ] Yes [ ] No