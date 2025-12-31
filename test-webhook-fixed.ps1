# Send Test Webhook - Fixed Version
param(
    [string]$WebhookUrl = "https://xchessacademy.com/webhook/public"
)

# Generate test data with current timestamps
$randomId = Get-Random -Maximum 999999
$currentTimestamp = [int][double]::Parse((Get-Date -UFormat %s))
$createdTimestamp = $currentTimestamp - 300
$viewedTimestamp = $currentTimestamp - 240
$pendingTimestamp = $currentTimestamp - 60
$dateTimeFormatted = Get-Date -Format "yyyyMMddHHmmss"

# Create test JSON payload
$testPayload = @{
    "id" = "test-purchase-exact-$randomId"
    "event_type" = "purchase.paid"
    "status" = "paid"
    "is_test" = $true
    "payment" = @{
        "amount" = 750
        "paid_on" = $currentTimestamp
        "currency" = "MYR"
        "fee_amount" = 23
        "net_amount" = 727
        "payment_type" = "purchase"
    }
    "client" = @{
        "email" = "test-webhook@example.com"
        "full_name" = "Test Webhook Customer"
        "phone" = "+60123456789"
    }
    "purchase" = @{
        "total" = 750
        "currency" = "MYR"
        "products" = @(
            @{
                "name" = "Test Product - Webhook Automation Test"
                "price" = 750
                "category" = "Test Category"
                "quantity" = "1.0000"
            }
        )
    }
    "transaction_data" = @{
        "payment_method" = "fpx"
        "processing_tx_id" = "TEST-$randomId"
        "country" = "MY"
        "attempts" = @(
            @{
                "extra" = @{
                    "webhook_payload" = @{
                        "fpx_fpxTxnId" = @("TEST-FPX-$randomId")
                        "fpx_buyerBankBranch" = @("TEST BANK FOR WEBHOOK")
                        "fpx_buyerBankId" = @("TEST001")
                    }
                }
            }
        )
    }
    "created_on" = $createdTimestamp
    "updated_on" = $currentTimestamp
}

$jsonPayload = $testPayload | ConvertTo-Json -Depth 10

Write-Host "🚀 Sending test webhook to: $WebhookUrl" -ForegroundColor Green
Write-Host "📦 Test Type: purchase.paid (exact format)" -ForegroundColor Cyan
Write-Host "💰 Amount: RM 7.50" -ForegroundColor Yellow
Write-Host "📧 Customer: Test Webhook Customer" -ForegroundColor Magenta
Write-Host "🔢 Purchase ID: test-purchase-exact-$randomId" -ForegroundColor Blue
Write-Host ""

try {
    $headers = @{
        'Content-Type' = 'application/json'
        'User-Agent' = 'Chip-Webhook-Test/1.0'
    }
    
    Write-Host "⏳ Sending webhook to xchessacademy.com..." -ForegroundColor Yellow
    
    $response = Invoke-RestMethod -Uri $WebhookUrl -Method POST -Body $jsonPayload -Headers $headers -TimeoutSec 30
    
    Write-Host "✅ Webhook sent successfully!" -ForegroundColor Green
    Write-Host "📋 Response:" -ForegroundColor Cyan
    
    if ($response) {
        Write-Host ($response | ConvertTo-Json -Depth 3) -ForegroundColor Gray
    } else {
        Write-Host "No response body (webhook processed successfully)" -ForegroundColor Gray
    }
    
    Write-Host ""
    Write-Host "✅ Next Steps:" -ForegroundColor Green
    Write-Host "1. Check webhook relay logs" -ForegroundColor White
    Write-Host "2. Verify webhook forwarded to Coda.io" -ForegroundColor White
    Write-Host "3. Check Coda Webhook Triggers table" -ForegroundColor White
    Write-Host "4. Verify payment record created" -ForegroundColor White
    Write-Host "5. Confirm amount shows as RM 7.50" -ForegroundColor White
    
} catch {
    Write-Host "❌ Error sending webhook:" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    
    if ($_.Exception.Response) {
        $statusCode = $_.Exception.Response.StatusCode.Value__
        Write-Host "HTTP Status Code: $statusCode" -ForegroundColor Red
    }
}

Write-Host ""
Write-Host "📝 Check logs with: Get-Content logs\webhook.txt -Tail 20" -ForegroundColor Blue