# Coda Automation Guide: Processing Chip Payment Webhooks

This guide shows you how to automatically process Chip payment webhook data in Coda.io using built-in automation features. Your webhook relay will continue to forward data to Coda unchanged - this guide focuses on the Coda-side processing.

## Overview

Your current flow:
```
Chip Gateway → Your Webhook Relay → Coda.io Webhook → Coda Automation → Payment Records Table
```

We'll set up Coda to automatically:
1. Receive webhook data from your relay
2. Filter for payment events (paid, cancelled, failed)
3. Extract payment information from the JSON
4. Store records in a structured payments table

## Step 1: Create Payment Records Table

### 1.1 Create New Table
In your Coda document:
1. Create a new table called **"Payment Records"**
2. Set up the following columns:

| Column Name | Type | Description |
|-------------|------|-------------|
| Purchase ID | Text | Unique transaction ID from Chip |
| Status | Select List | paid, cancelled, failed |
| Event Type | Text | Full event type (e.g., purchase.paid) |
| Amount (RM) | Currency | Payment amount in Ringgit |
| Currency | Text | Currency code (MYR) |
| Fee Amount (RM) | Currency | Processing fee |
| Net Amount (RM) | Currency | Net amount after fees |
| Payment Method | Text | Payment method (fpx, card, etc.) |
| Transaction ID | Text | External transaction reference |
| Customer Email | Email | Customer's email address |
| Customer Name | Text | Customer's full name |
| Product Name | Text | Name of purchased product/service |
| Payment Date | DateTime | When payment was completed |
| Is Test | Checkbox | Whether this is a test transaction |
| FPX Transaction ID | Text | FPX-specific transaction ID |
| Bank Name | Text | Customer's bank (for FPX payments) |
| Raw JSON | Text | Complete webhook payload for reference |
| Created At | DateTime | When record was created in Coda |

### 1.2 Configure Select List for Status
For the **Status** column:
1. Click the column settings
2. Add these options:
   - `paid` (green color)
   - `cancelled` (orange color) 
   - `failed` (red color)

## Step 2: Set Up Webhook Reception

### 2.1 Create Webhook Trigger Table
Create a helper table called **"Webhook Triggers"**:

| Column Name | Type | Description |
|-------------|------|-------------|
| Webhook ID | Text | Auto-generated ID |
| Received At | DateTime | When webhook was received |
| Raw Data | Text | Complete JSON payload |
| Event Type | Text | Extracted event type |
| Processed | Checkbox | Whether this webhook was processed |
| Processing Notes | Text | Any processing errors or notes |

### 2.2 Configure Webhook URL
1. In your Coda document, go to **Settings** → **Webhooks**
2. Create a new webhook that adds rows to the **"Webhook Triggers"** table
3. Use this webhook URL in your relay configuration as the `CODA_WEBHOOK_URL`

## Step 3: Create Automation Rules

### 3.1 Main Processing Automation
Create an automation rule that triggers when new rows are added to "Webhook Triggers":

**Trigger:** When rows are added to "Webhook Triggers"

**Conditions:** 
- `Raw Data` contains "purchase.paid" OR "purchase.cancelled" OR "purchase.failed"

**Actions:** Run this formula to process the webhook:

```javascript
// Parse the JSON data
let jsonData = ParseJSON([Raw Data])

// Extract event type to determine if we should process
let eventType = jsonData.event_type

// Only process payment completion events
if (eventType.Contains("purchase.paid") OR eventType.Contains("purchase.cancelled") OR eventType.Contains("purchase.failed")) {
  
  // Determine status from event type
  let status = 
    if(eventType = "purchase.paid", "paid",
      if(eventType = "purchase.cancelled", "cancelled",
        if(eventType = "purchase.failed", "failed", "unknown")))
  
  // Extract payment data
  let purchaseId = jsonData.id
  let amount = jsonData.payment.amount / 100  // Convert from cents to RM
  let currency = jsonData.payment.currency
  let feeAmount = jsonData.payment.fee_amount / 100
  let netAmount = jsonData.payment.net_amount / 100
  let paymentMethod = jsonData.transaction_data.payment_method
  let transactionId = jsonData.transaction_data.processing_tx_id
  let customerEmail = jsonData.client.email
  let customerName = jsonData.client.full_name
  let isTest = jsonData.is_test
  
  // Extract product info (first product)
  let productName = ""
  if (jsonData.purchase.products.Count() > 0) {
    productName = jsonData.purchase.products.First().name
  }
  
  // Convert payment timestamp
  let paymentDate = ""
  if (jsonData.payment.paid_on > 0) {
    paymentDate = DateTime(jsonData.payment.paid_on)
  } else if (jsonData.created_on > 0) {
    paymentDate = DateTime(jsonData.created_on)
  }
  
  // Extract FPX data if available
  let fpxTransactionId = ""
  let bankName = ""
  if (jsonData.transaction_data.attempts.Count() > 0) {
    let attempt = jsonData.transaction_data.attempts.First()
    if (attempt.extra.webhook_payload.fpx_fpxTxnId.Count() > 0) {
      fpxTransactionId = attempt.extra.webhook_payload.fpx_fpxTxnId.First()
    }
    if (attempt.extra.webhook_payload.fpx_buyerBankBranch.Count() > 0) {
      bankName = attempt.extra.webhook_payload.fpx_buyerBankBranch.First()
    }
  }
  
  // Check if record already exists (prevent duplicates)
  let existingRecord = [Payment Records].Filter([Purchase ID] = purchaseId)
  
  if (existingRecord.Count() = 0) {
    // Add new payment record
    AddRow([Payment Records], 
      [Purchase ID], purchaseId,
      [Status], status,
      [Event Type], eventType,
      [Amount (RM)], amount,
      [Currency], currency,
      [Fee Amount (RM)], feeAmount,
      [Net Amount (RM)], netAmount,
      [Payment Method], paymentMethod,
      [Transaction ID], transactionId,
      [Customer Email], customerEmail,
      [Customer Name], customerName,
      [Product Name], productName,
      [Payment Date], paymentDate,
      [Is Test], isTest,
      [FPX Transaction ID], fpxTransactionId,
      [Bank Name], bankName,
      [Raw JSON], [Raw Data],
      [Created At], Now()
    )
  } else {
    // Update existing record
    ModifyRows(existingRecord,
      [Status], status,
      [Event Type], eventType,
      [Amount (RM)], amount,
      [Fee Amount (RM)], feeAmount,
      [Net Amount (RM)], netAmount,
      [Payment Date], paymentDate,
      [Raw JSON], [Raw Data]
    )
  }
  
  // Mark webhook as processed
  ModifyRows(thisRow, [Processed], true, [Processing Notes], "Successfully processed")
  
} else {
  // Mark as processed but not relevant
  ModifyRows(thisRow, [Processed], true, [Processing Notes], "Skipped - not a payment completion event")
}
```

### 3.2 Error Handling Automation
Create a second automation for error handling:

**Trigger:** When rows are modified in "Webhook Triggers"
**Condition:** `Processed` is false AND `Created At` is more than 5 minutes ago

**Action:**
```javascript
ModifyRows(thisRow, 
  [Processed], true, 
  [Processing Notes], "Processing failed - check Raw Data format"
)
```

## Step 4: Set Up Monitoring and Alerts

### 4.1 Create Dashboard Views
Create these views in your Payment Records table:

1. **Recent Payments** - Last 24 hours, sorted by Payment Date
2. **Failed Payments** - Status = "failed", needs attention
3. **Test Payments** - Is Test = true, for debugging
4. **Payment Summary** - Grouped by Status with count and total amounts

### 4.2 Set Up Email Notifications
Create automation rules for important events:

**For Failed Payments:**
```javascript
// Trigger: When rows added to Payment Records
// Condition: Status = "failed"
// Action: Send email notification

SendEmail("your-email@domain.com", 
  "Payment Failed Alert", 
  Format("Payment failed for {1} - Amount: RM{2} - Customer: {3}", 
    [Purchase ID], 
    [Amount (RM)], 
    [Customer Email]
  )
)
```

**For High-Value Payments:**
```javascript
// Trigger: When rows added to Payment Records  
// Condition: Status = "paid" AND Amount (RM) > 1000
// Action: Send notification for large payments

SendEmail("finance@yourdomain.com",
  "Large Payment Received",
  Format("Large payment received: RM{1} from {2} for {3}",
    [Amount (RM)],
    [Customer Name],
    [Product Name]
  )
)
```

## Step 5: Testing Your Setup

### 5.1 Test with Sample Data
Use this sample JSON to test your automation (paste into Webhook Triggers Raw Data):

```json
{
  "id": "test-purchase-123",
  "event_type": "purchase.paid",
  "status": "paid",
  "is_test": true,
  "payment": {
    "amount": 1500,
    "currency": "MYR",
    "fee_amount": 45,
    "net_amount": 1455,
    "paid_on": 1757345632,
    "payment_type": "purchase"
  },
  "client": {
    "email": "test@example.com",
    "full_name": "Test Customer"
  },
  "purchase": {
    "products": [
      {
        "name": "Test Product",
        "category": "Digital Service"
      }
    ]
  },
  "transaction_data": {
    "payment_method": "fpx",
    "processing_tx_id": "TXN123456",
    "attempts": [
      {
        "extra": {
          "webhook_payload": {
            "fpx_fpxTxnId": ["FPX789"],
            "fpx_buyerBankBranch": ["Test Bank"]
          }
        }
      }
    ]
  },
  "created_on": 1757345560
}
```

### 5.2 Verification Checklist
After testing, verify:
- [ ] Payment record is created automatically
- [ ] All fields are populated correctly
- [ ] Amount is converted from cents to RM (15.00)
- [ ] Status is set correctly ("paid")
- [ ] Webhook is marked as processed
- [ ] No duplicate records are created

## Step 6: Advanced Features

### 6.1 Payment Analytics
Create calculated columns for insights:

**Monthly Revenue:**
```javascript
[Payment Records].Filter([Payment Date].Month() = Today().Month() AND [Status] = "paid").[Amount (RM)].Sum()
```

**Success Rate:**
```javascript
[Payment Records].Filter([Status] = "paid").Count() / [Payment Records].Count() * 100
```

### 6.2 Customer Management
Link to a separate Customers table:
```javascript
// In Payment Records table, create a relation to Customers table
LookupOrAddRow([Customers], [Email], [Customer Email], 
  [Name], [Customer Name],
  [Total Payments], [Customers].[Payment Records].Filter([Status] = "paid").[Amount (RM)].Sum()
)
```

## Step 7: Maintenance

### 7.1 Regular Cleanup
Set up monthly automation to archive old webhook triggers:
```javascript
// Delete processed webhook triggers older than 30 days
let oldWebhooks = [Webhook Triggers].Filter([Created At] < Today().AddDays(-30) AND [Processed] = true)
DeleteRows(oldWebhooks)
```

### 7.2 Monitoring
Create a status dashboard showing:
- Total webhooks received today
- Processing success rate
- Failed payment count
- Average processing time

## Troubleshooting

### Common Issues:

1. **Automation not triggering:** Check webhook URL configuration
2. **JSON parsing errors:** Verify Raw Data contains valid JSON
3. **Missing fields:** Check field names match exactly
4. **Duplicate records:** Ensure Purchase ID uniqueness check is working
5. **Wrong amounts:** Verify division by 100 for cent conversion

### Debug Mode:
Add this to your automation for debugging:
```javascript
// Add debug info to Processing Notes
let debugInfo = Format("Event: {1}, Amount: {2}, Customer: {3}", eventType, amount, customerEmail)
ModifyRows(thisRow, [Processing Notes], debugInfo)
```

## Summary

This setup provides automatic processing of Chip payment webhooks with:
- ✅ Automatic filtering for payment events (paid/cancelled/failed)
- ✅ Complete payment data extraction and storage
- ✅ Error handling and monitoring
- ✅ Real-time notifications for important events
- ✅ Analytics and reporting capabilities
- ✅ Duplicate prevention and data integrity

Your webhook relay continues to work unchanged, and all processing happens within Coda using their native automation features.