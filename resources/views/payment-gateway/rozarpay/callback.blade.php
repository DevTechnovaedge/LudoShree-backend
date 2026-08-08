<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Response</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .response-card {
            border: none;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        .success-icon {
            font-size: 50px;
            color: #28a745;
        }
        .error-icon {
            font-size: 50px;
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="response-card bg-white text-center">
        @if(empty($record))
            <div id="invalidRef">
                <div class="error-icon mb-3">&#9888;</div>
                <h2>Unable to verify payment</h2>
                <p>We could not load this transaction. If money was deducted, contact support with your payment receipt.</p>
            </div>
        @else
            @php $st = (int) ($record->status ?? 0); @endphp

            @if($st === 0)
                <div id="pendingMessage">
                    <div class="success-icon mb-3">&#8987;</div>
                    <h2>Transaction Pending!</h2>
                    <p>Your payment is processing.</p>
                </div>
            @elseif($st === 1)
                <div id="successMessage">
                    <div class="success-icon mb-3">&#10003;</div>
                    <h2>Transaction Successful!</h2>
                    <p>Your payment has been processed successfully.</p>
                </div>
            @elseif($st === 2)
                <div id="errorMessage">
                    <div class="error-icon mb-3">&#10060;</div>
                    <h2>Transaction Failed!</h2>
                    <p>Unfortunately, your payment could not be processed. Please try again.</p>
                </div>
            @elseif($st === 5)
                <div id="cancelMessage">
                    <div class="error-icon mb-3">&#10060;</div>
                    <h2>Transaction Cancelled!</h2>
                    <p>Unfortunately, your payment could not be processed.</p>
                </div>
            @else
                <div id="processingMessage">
                    <div class="success-icon mb-3">&#8987;</div>
                    <h2>Processing</h2>
                    <p>We're verifying your payment. Your wallet will update shortly.</p>
                </div>
            @endif
        @endif
    </div>
</body>
</html>
