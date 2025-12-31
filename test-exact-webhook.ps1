# Send Exact Test Webhook - Uses your actual Chip JSON format
# This sends the exact JSON structure from your raw file with test modifications

param(
    [string]$WebhookUrl = "http://localhost/webhook/chip"
)

# Your exact JSON structure with test modifications
$exactJsonPayload = @'
{
    "id": "test-purchase-exact-{RANDOM}",
    "due": 1757349160,
    "type": "purchase",
    "client": {
        "cc": [],
        "bcc": [],
        "city": "",
        "email": "test-webhook@example.com",
        "phone": "",
        "state": "",
        "country": "",
        "zip_code": "",
        "bank_code": "",
        "full_name": "Test Webhook Customer",
        "brand_name": "",
        "legal_name": "",
        "tax_number": "",
        "client_type": null,
        "bank_account": "",
        "personal_code": "",
        "shipping_city": "",
        "shipping_state": "",
        "street_address": "",
        "delivery_methods": [
            {
                "method": "email",
                "options": {}
            }
        ],
        "shipping_country": "",
        "shipping_zip_code": "",
        "registration_number": "",
        "shipping_street_address": ""
    },
    "issued": "2025-09-17",
    "status": "paid",
    "is_test": true,
    "payment": {
        "amount": 750,
        "paid_on": {TIMESTAMP},
        "currency": "MYR",
        "fee_amount": 23,
        "net_amount": 727,
        "description": "Test webhook payment",
        "is_outgoing": false,
        "payment_type": "purchase",
        "pending_amount": 0,
        "remote_paid_on": {TIMESTAMP},
        "owned_bank_code": null,
        "owned_bank_account": null,
        "pending_unfreeze_on": null,
        "owned_bank_account_id": null
    },
    "product": "purchases",
    "user_id": null,
    "brand_id": "47f029ab-98d3-4ff1-84ba-c0153c00abdb",
    "order_id": null,
    "platform": "api",
    "purchase": {
        "debt": 0,
        "notes": "Test webhook purchase",
        "total": 750,
        "currency": "MYR",
        "language": "en",
        "metadata": {},
        "products": [
            {
                "name": "Test Product - Webhook Automation Test",
                "price": 750,
                "category": "Test Category",
                "discount": 0,
                "quantity": "1.0000",
                "tax_percent": "0.00",
                "total_price_override": null
            }
        ],
        "timezone": "UTC",
        "due_strict": false,
        "email_message": "Thank you for your test purchase",
        "total_override": null,
        "shipping_options": [],
        "subtotal_override": null,
        "total_tax_override": null,
        "has_upsell_products": false,
        "payment_method_details": {},
        "request_client_details": [],
        "total_discount_override": null
    },
    "client_id": "test-client-{RANDOM}",
    "reference": "INV-2026-01-{RANDOM}",
    "viewed_on": {VIEWED_TIMESTAMP},
    "company_id": "60be6bf5-62c1-4444-b604-bd3beecee58e",
    "created_on": {CREATED_TIMESTAMP},
    "event_type": "purchase.paid",
    "updated_on": {TIMESTAMP},
    "invoice_url": null,
    "can_retrieve": false,
    "checkout_url": "https://gate.chip-in.asia/p/test-purchase-exact-{RANDOM}/invoice/",
    "send_receipt": false,
    "skip_capture": false,
    "creator_agent": "Webhook Test Script",
    "referral_code": null,
    "can_chargeback": false,
    "issuer_details": {
        "website": "https://test-webhook.com/",
        "brand_name": "WEBHOOK TEST COMPANY",
        "legal_city": "KUALA LUMPUR",
        "legal_name": "WEBHOOK TEST COMPANY",
        "tax_number": "",
        "bank_accounts": [
            {
                "bank_code": "TESTBANK",
                "bank_account": "1234567890"
            }
        ],
        "legal_country": "MY",
        "legal_zip_code": "50000",
        "registration_number": "TEST123456",
        "legal_street_address": "123 TEST STREET, KUALA LUMPUR"
    },
    "marked_as_paid": false,
    "status_history": [
        {
            "status": "created",
            "timestamp": {CREATED_TIMESTAMP}
        },
        {
            "status": "viewed",
            "timestamp": {VIEWED_TIMESTAMP}
        },
        {
            "status": "pending_execute",
            "timestamp": {PENDING_TIMESTAMP}
        },
        {
            "status": "paid",
            "timestamp": {TIMESTAMP}
        }
    ],
    "cancel_redirect": "",
    "created_from_ip": "127.0.0.1",
    "direct_post_url": null,
    "force_recurring": false,
    "recurring_token": null,
    "failure_redirect": "",
    "success_callback": "",
    "success_redirect": "",
    "transaction_data": {
        "flow": "payform",
        "extra": {
            "webhook_payload": {
                "fpx_msgType": [
                    "AC"
                ],
                "fpx_checkSum": [
                    "TEST_CHECKSUM_FOR_WEBHOOK_TESTING"
                ],
                "fpx_fpxTxnId": [
                    "TEST{RANDOM}"
                ],
                "fpx_msgToken": [
                    "01"
                ],
                "fpx_sellerId": [
                    "SE00096083"
                ],
                "fpx_buyerName": [
                    "TEST WEBHOOK CUSTOMER"
                ],
                "fpx_txnAmount": [
                    "7.50"
                ],
                "fpx_fpxTxnTime": [
                    "{DATE_TIME}"
                ],
                "fpx_sellerExId": [
                    "EX00012233"
                ],
                "fpx_buyerBankId": [
                    "TEST001"
                ],
                "fpx_debitAuthNo": [
                    "TEST{RANDOM}"
                ],
                "fpx_txnCurrency": [
                    "MYR"
                ],
                "fpx_creditAuthNo": [
                    "9999999999"
                ],
                "fpx_debitAuthCode": [
                    "00"
                ],
                "fpx_sellerOrderNo": [
                    "test-purchase-exact-{RANDOM}"
                ],
                "fpx_sellerTxnTime": [
                    "{DATE_TIME}"
                ],
                "fpx_creditAuthCode": [
                    "00"
                ],
                "fpx_buyerBankBranch": [
                    "TEST BANK FOR WEBHOOK"
                ],
                "fpx_sellerExOrderNo": [
                    "TEST{RANDOM}"
                ]
            }
        },
        "country": "MY",
        "attempts": [
            {
                "flow": "payform",
                "type": "execute",
                "error": null,
                "extra": {
                    "webhook_payload": {
                        "fpx_msgType": [
                            "AC"
                        ],
                        "fpx_checkSum": [
                            "TEST_CHECKSUM_FOR_WEBHOOK_TESTING"
                        ],
                        "fpx_fpxTxnId": [
                            "TEST{RANDOM}"
                        ],
                        "fpx_msgToken": [
                            "01"
                        ],
                        "fpx_sellerId": [
                            "SE00096083"
                        ],
                        "fpx_buyerName": [
                            "TEST WEBHOOK CUSTOMER"
                        ],
                        "fpx_txnAmount": [
                            "7.50"
                        ],
                        "fpx_fpxTxnTime": [
                            "{DATE_TIME}"
                        ],
                        "fpx_sellerExId": [
                            "EX00012233"
                        ],
                        "fpx_buyerBankId": [
                            "TEST001"
                        ],
                        "fpx_debitAuthNo": [
                            "TEST{RANDOM}"
                        ],
                        "fpx_txnCurrency": [
                            "MYR"
                        ],
                        "fpx_creditAuthNo": [
                            "9999999999"
                        ],
                        "fpx_debitAuthCode": [
                            "00"
                        ],
                        "fpx_sellerOrderNo": [
                            "test-purchase-exact-{RANDOM}"
                        ],
                        "fpx_sellerTxnTime": [
                            "{DATE_TIME}"
                        ],
                        "fpx_creditAuthCode": [
                            "00"
                        ],
                        "fpx_buyerBankBranch": [
                            "TEST BANK FOR WEBHOOK"
                        ],
                        "fpx_sellerExOrderNo": [
                            "TEST{RANDOM}"
                        ]
                    }
                },
                "country": "MY",
                "client_ip": "127.0.0.1",
                "fee_amount": 23,
                "successful": true,
                "payment_method": "fpx",
                "processing_time": {TIMESTAMP},
                "processing_tx_id": "TEST{RANDOM}"
            }
        ],
        "payment_method": "fpx",
        "processing_tx_id": "TEST{RANDOM}"
    },
    "upsell_campaigns": [],
    "refundable_amount": 750,
    "is_recurring_token": false,
    "billing_template_id": null,
    "currency_conversion": null,
    "reference_generated": "TEST{RANDOM}",
    "refund_availability": "all",
    "referral_campaign_id": null,
    "retain_level_details": null,
    "referral_code_details": null,
    "referral_code_generated": null,
    "payment_method_whitelist": null
}
'@

# Replace placeholders with actual values
$randomId = Get-Random -Maximum 999999
$currentTimestamp = [int][double]::Parse((Get-Date -UFormat %s))
$createdTimestamp = $currentTimestamp - 300  # 5 minutes ago
$viewedTimestamp = $currentTimestamp - 240   # 4 minutes ago
$pendingTimestamp = $currentTimestamp - 60   # 1 minute ago
$dateTimeFormatted = Get-Date -Format "yyyyMMddHHmmss"

$finalJsonPayload = $exactJsonPayload -replace "\{RANDOM\}", $randomId -replace "\{TIMESTAMP\}", $currentTimestamp -replace "\{CREATED_TIMESTAMP\}", $createdTimestamp -replace "\{VIEWED_TIMESTAMP\}", $viewedTimestamp -replace "\{PENDING_TIMESTAMP\}", $pendingTimestamp -replace "\{DATE_TIME\}", $dateTimeFormatted

Write-Host "🚀 Sending EXACT test webhook to: $WebhookUrl" -ForegroundColor Green
Write-Host "📦 Test Type: purchase.paid (exact format)" -ForegroundColor Cyan
Write-Host "💰 Amount: RM 7.50" -ForegroundColor Yellow
Write-Host "📧 Customer: Test Webhook Customer (test-webhook@example.com)" -ForegroundColor Magenta
Write-Host "🔢 Purchase ID: test-purchase-exact-$randomId" -ForegroundColor Blue
Write-Host ""

try {
    $headers = @{
        'Content-Type' = 'application/json'
        'User-Agent' = 'Chip-Webhook-Test/1.0'
    }
    
    Write-Host "⏳ Sending exact format webhook..." -ForegroundColor Yellow
    
    $response = Invoke-RestMethod -Uri $WebhookUrl -Method POST -Body $finalJsonPayload -Headers $headers -TimeoutSec 30
    
    Write-Host "✅ Webhook sent successfully!" -ForegroundColor Green
    Write-Host "📋 Response:" -ForegroundColor Cyan
    
    if ($response) {
        Write-Host ($response | ConvertTo-Json -Depth 3) -ForegroundColor Gray
    } else {
        Write-Host "No response body received (webhook processed successfully)" -ForegroundColor Gray
    }
    
    Write-Host ""
    Write-Host "✅ Next Steps:" -ForegroundColor Green
    Write-Host "1. Check your webhook relay logs in the logs/ directory" -ForegroundColor White
    Write-Host "2. Verify the webhook was forwarded to Coda.io" -ForegroundColor White
    Write-Host "3. Check your Coda 'Webhook Triggers' table for new entries" -ForegroundColor White
    Write-Host "4. Verify Coda automation created a payment record" -ForegroundColor White
    Write-Host "5. Confirm amount shows as RM 7.50 in Payment Records" -ForegroundColor White
    
} catch {
    Write-Host "❌ Error sending webhook:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $statusCode = $_.Exception.Response.StatusCode.Value__
        Write-Host "HTTP Status Code: $statusCode" -ForegroundColor Red
        
        Write-Host ""
        Write-Host "🔧 Troubleshooting:" -ForegroundColor Yellow
        Write-Host "1. Make sure your webhook relay is running" -ForegroundColor White
        Write-Host "2. Verify the webhook URL is correct: $WebhookUrl" -ForegroundColor White
        Write-Host "3. Check if your server is accessible" -ForegroundColor White
        Write-Host "4. Review webhook relay logs for errors" -ForegroundColor White
    }
}

Write-Host ""
Write-Host "📝 You can also check the logs:" -ForegroundColor Blue
Write-Host "Get-Content logs\webhook.txt -Tail 20" -ForegroundColor Gray