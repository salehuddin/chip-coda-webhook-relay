# Test Webhook Script for Chip-to-Coda Webhook Relay
# This script sends a test webhook using the actual Chip JSON format

param(
    [string]$WebhookUrl = "http://localhost/webhook/chip",
    [string]$TestType = "paid"
)

# Get current timestamp
$currentTimestamp = [int][double]::Parse((Get-Date -UFormat %s))

# Test data based on your actual Chip JSON, with variations for different test types
$testData = @{
    "paid" = @{
        "id" = "test-purchase-$(Get-Random)"
        "event_type" = "purchase.paid"
        "status" = "paid"
        "is_test" = $true
        "payment" = @{
            "amount" = 500  # RM 5.00 for testing
            "paid_on" = $currentTimestamp
            "currency" = "MYR"
            "fee_amount" = 15  # RM 0.15
            "net_amount" = 485  # RM 4.85
            "payment_type" = "purchase"
        }
        "client" = @{
            "email" = "test@example.com"
            "full_name" = "Test Customer"
            "phone" = "+60123456789"
        }
        "purchase" = @{
            "total" = 500
            "currency" = "MYR"
            "products" = @(
                @{
                    "name" = "Test Product - Webhook Test"
                    "price" = 500
                    "category" = "Test Category"
                    "quantity" = "1.0000"
                }
            )
        }
        "transaction_data" = @{
            "payment_method" = "fpx"
            "processing_tx_id" = "TEST-$(Get-Random)"
            "country" = "MY"
            "attempts" = @(
                @{
                    "flow" = "payform"
                    "type" = "execute"
                    "extra" = @{
                        "webhook_payload" = @{
                            "fpx_fpxTxnId" = @("TEST-FPX-$(Get-Random)")
                            "fpx_buyerBankBranch" = @("TEST BANK")
                            "fpx_buyerBankId" = @("TEST001")
                        }
                    }
                }
            )
        }
        "created_on" = $currentTimestamp - 60
        "updated_on" = $currentTimestamp
    }
    
    "cancelled" = @{
        "id" = "test-purchase-cancelled-$(Get-Random)"
        "event_type" = "purchase.cancelled"
        "status" = "cancelled"
        "is_test" = $true
        "payment" = @{
            "amount" = 1000  # RM 10.00
            "currency" = "MYR"
            "fee_amount" = 0
            "net_amount" = 0
            "payment_type" = "purchase"
        }
        "client" = @{
            "email" = "cancelled-test@example.com"
            "full_name" = "Cancelled Test Customer"
        }
        "purchase" = @{
            "total" = 1000
            "currency" = "MYR"
            "products" = @(
                @{
                    "name" = "Cancelled Test Product"
                    "price" = 1000
                    "category" = "Test"
                    "quantity" = "1.0000"
                }
            )
        }
        "transaction_data" = @{
            "payment_method" = "fpx"
            "country" = "MY"
        }
        "created_on" = $currentTimestamp - 300
        "updated_on" = $currentTimestamp
    }
    
    "failed" = @{
        "id" = "test-purchase-failed-$(Get-Random)"
        "event_type" = "purchase.failed"
        "status" = "failed"
        "is_test" = $true
        "payment" = @{
            "amount" = 2500  # RM 25.00
            "currency" = "MYR"
            "fee_amount" = 0
            "net_amount" = 0
            "payment_type" = "purchase"
        }
        "client" = @{
            "email" = "failed-test@example.com"
            "full_name" = "Failed Test Customer"
        }
        "purchase" = @{
            "total" = 2500
            "currency" = "MYR"
            "products" = @(
                @{
                    "name" = "Failed Test Product"
                    "price" = 2500
                    "category" = "Test"
                    "quantity" = "1.0000"
                }
            )
        }
        "transaction_data" = @{
            "payment_method" = "fpx"
            "country" = "MY"
        }
        "created_on" = $currentTimestamp - 120
        "updated_on" = $currentTimestamp
    }
}

# Select test data based on type
$webhookData = $testData[$TestType]

# Convert to JSON
$jsonPayload = $webhookData | ConvertTo-Json -Depth 10

Write-Host "🚀 Sending test webhook to: $WebhookUrl" -ForegroundColor Green
Write-Host "📦 Test Type: $TestType" -ForegroundColor Cyan
Write-Host "💰 Amount: RM $($webhookData.payment.amount / 100)" -ForegroundColor Yellow
Write-Host "📧 Customer: $($webhookData.client.full_name) ($($webhookData.client.email))" -ForegroundColor Magenta
Write-Host ""

try {
    # Send webhook using Invoke-RestMethod
    $headers = @{
        'Content-Type' = 'application/json'
        'User-Agent' = 'Chip-Webhook-Test/1.0'
    }
    
    Write-Host "⏳ Sending webhook..." -ForegroundColor Yellow
    
    $response = Invoke-RestMethod -Uri $WebhookUrl -Method POST -Body $jsonPayload -Headers $headers -TimeoutSec 30
    
    Write-Host "✅ Webhook sent successfully!" -ForegroundColor Green
    Write-Host "📋 Response:" -ForegroundColor Cyan
    Write-Host ($response | ConvertTo-Json -Depth 3) -ForegroundColor Gray
    
} catch {
    Write-Host "❌ Error sending webhook:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $statusCode = $_.Exception.Response.StatusCode.Value__
        Write-Host "HTTP Status Code: $statusCode" -ForegroundColor Red
        
        try {
            $responseBody = $_.Exception.Response.GetResponseStream()
            $reader = New-Object System.IO.StreamReader($responseBody)
            $errorResponse = $reader.ReadToEnd()
            Write-Host "Response Body: $errorResponse" -ForegroundColor Red
        } catch {
            Write-Host "Could not read error response body" -ForegroundColor Red
        }
    }
}

Write-Host ""
Write-Host "📝 Raw JSON payload sent:" -ForegroundColor Blue
Write-Host $jsonPayload -ForegroundColor Gray