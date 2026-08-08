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
        @if($record->status == 0)
        <!-- Success Message -->
        <div id="successMessage">
            <div class="success-icon mb-3">⏳</div>
            <h2>Transaction Pending!</h2>
            <p>Your payment has been processing.</p>
        </div>
        @endif


        @if($record->status == 1)
        <!-- Success Message -->
        <div id="successMessage">
            <div class="success-icon mb-3">&#10003;</div>
            <h2>Transaction Successful!</h2>
            <p>Your payment has been processed successfully.</p>
        </div>
        @endif

        @if($record->status == 2)
        <!-- Error Message -->
        <div id="errorMessage">
            <div class="error-icon mb-3">&#10060;</div>
            <h2>Transaction Failed!</h2>
            <p>Unfortunately, your payment could not be processed. Please try again.</p>
        </div>
        @endif
    </div>
</body>
</html>
