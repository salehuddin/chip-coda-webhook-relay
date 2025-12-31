# Chip Webhook Data Reference

Quick reference for extracting payment data from Chip webhooks in Coda automation.

## Key Data Fields

### Core Payment Information
```javascript
// Purchase ID (unique identifier)
jsonData.id  // "0da9058d-8917-4702-9395-8293da6dfb75"

// Payment Status
jsonData.status  // "paid", "cancelled", "failed"

// Event Type (more specific)
jsonData.event_type  // "purchase.paid", "purchase.cancelled", "purchase.failed"

// Test Transaction Flag
jsonData.is_test  // true/false
```

### Amount Information (All in Cents)
```javascript
// Payment amount in cents (divide by 100 for RM)
jsonData.payment.amount  // 100 = RM 1.00

// Currency
jsonData.payment.currency  // "MYR"

// Processing fee in cents
jsonData.payment.fee_amount  // 45 = RM 0.45

// Net amount after fees in cents
jsonData.payment.net_amount  // 55 = RM 0.55

// Payment type
jsonData.payment.payment_type  // "purchase"
```

### Payment Timestamps
```javascript
// When payment was completed (Unix timestamp)
jsonData.payment.paid_on  // 1757345632

// When transaction was created (Unix timestamp)  
jsonData.created_on  // 1757345560

// When transaction was last updated
jsonData.updated_on  // 1757345632

// Convert to DateTime in Coda:
DateTime(jsonData.payment.paid_on)
```

### Customer Information
```javascript
// Customer email
jsonData.client.email  // "customer@email.com"

// Customer full name
jsonData.client.full_name  // "John Doe"

// Customer phone (may be empty)
jsonData.client.phone  // "+60123456789"

// Customer address fields
jsonData.client.city
jsonData.client.state  
jsonData.client.country
jsonData.client.zip_code
jsonData.client.street_address
```

### Product Information
```javascript
// First product name
jsonData.purchase.products[0].name  // "Course Subscription"

// Product category
jsonData.purchase.products[0].category  // "Education"

// Product price (in cents)
jsonData.purchase.products[0].price  // 10000 = RM 100.00

// Product quantity
jsonData.purchase.products[0].quantity  // "1.0000"

// Total purchase amount
jsonData.purchase.total  // 10000 (in cents)

// Purchase currency
jsonData.purchase.currency  // "MYR"
```

### Transaction Details
```javascript
// Payment method
jsonData.transaction_data.payment_method  // "fpx", "card", etc.

// External transaction ID
jsonData.transaction_data.processing_tx_id  // "TXN123456"

// Processing country
jsonData.transaction_data.country  // "MY"
```

### FPX Specific Data (Malaysia)
```javascript
// FPX transaction ID
jsonData.transaction_data.attempts[0].extra.webhook_payload.fpx_fpxTxnId[0]  // "2509082332530562"

// Customer's bank
jsonData.transaction_data.attempts[0].extra.webhook_payload.fpx_buyerBankBranch[0]  // "CIMB BANK"

// Bank code
jsonData.transaction_data.attempts[0].extra.webhook_payload.fpx_buyerBankId[0]  // "BCBB0235"

// Authorization codes
jsonData.transaction_data.attempts[0].extra.webhook_payload.fpx_debitAuthCode[0]  // "00"
jsonData.transaction_data.attempts[0].extra.webhook_payload.fpx_debitAuthNo[0]  // "07573877"
```

## Event Types to Filter

Process only these event types:
- `purchase.paid` - Payment completed successfully
- `purchase.cancelled` - Payment was cancelled 
- `purchase.failed` - Payment failed

Ignore these event types:
- `purchase.created` - Transaction created (not completed)
- `purchase.viewed` - Customer viewed payment page
- `purchase.pending_execute` - Payment in progress

## Status Mapping

Map event_type to simple status:
```javascript
let status = 
  if(eventType = "purchase.paid", "paid",
    if(eventType = "purchase.cancelled", "cancelled",
      if(eventType = "purchase.failed", "failed", "unknown")))
```

## Amount Conversion

All amounts in Chip webhooks are in cents:
```javascript
// Convert cents to Ringgit Malaysia
let amountRM = jsonData.payment.amount / 100  // 1500 cents = RM 15.00
let feeRM = jsonData.payment.fee_amount / 100  // 45 cents = RM 0.45
let netRM = jsonData.payment.net_amount / 100  // 1455 cents = RM 14.55
```

## Sample Webhook Structure

```json
{
  "id": "purchase-id-here",
  "event_type": "purchase.paid",
  "status": "paid", 
  "is_test": false,
  "payment": {
    "amount": 1500,
    "currency": "MYR", 
    "fee_amount": 45,
    "net_amount": 1455,
    "paid_on": 1757345632,
    "payment_type": "purchase"
  },
  "client": {
    "email": "customer@email.com",
    "full_name": "Customer Name"
  },
  "purchase": {
    "total": 1500,
    "currency": "MYR",
    "products": [
      {
        "name": "Product Name",
        "price": 1500,
        "quantity": "1.0000",
        "category": "Service"
      }
    ]
  },
  "transaction_data": {
    "payment_method": "fpx",
    "processing_tx_id": "TXN123456",
    "country": "MY"
  },
  "created_on": 1757345560,
  "updated_on": 1757345632
}
```

## Coda Formula Examples

### Extract Core Data
```javascript
let purchaseId = jsonData.id
let status = if(jsonData.event_type = "purchase.paid", "paid", 
             if(jsonData.event_type = "purchase.cancelled", "cancelled", "failed"))
let amount = jsonData.payment.amount / 100
let customerEmail = jsonData.client.email
let productName = jsonData.purchase.products.First().name
let paymentDate = DateTime(jsonData.payment.paid_on)
```

### Check for Valid Payment Event
```javascript
let eventType = jsonData.event_type
let isPaymentEvent = eventType.Contains("purchase.paid") OR 
                    eventType.Contains("purchase.cancelled") OR 
                    eventType.Contains("purchase.failed")
```

### Safe Field Access (Handle Missing Data)
```javascript
let customerName = if(jsonData.client.full_name.IsNotBlank(), jsonData.client.full_name, "")
let productName = if(jsonData.purchase.products.Count() > 0, 
                    jsonData.purchase.products.First().name, 
                    "")
let transactionId = if(jsonData.transaction_data.processing_tx_id.IsNotBlank(),
                      jsonData.transaction_data.processing_tx_id,
                      "")
```