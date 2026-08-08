<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razorpay Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>
<body>
    <h2>Razorpay Payment Integration</h2>
    <button id="pay-button">Pay ₹100</button>

    <script>
        document.getElementById('pay-button').onclick = function(e) {
            e.preventDefault();

            var options = {
                "key": "{{ site_setting()->rozarpay_api_key }}", // ✅ Replace with your Razorpay Key ID
                "amount": 10000, // ₹100 in paise
                "currency": "INR",
                "name": "Your Business Name",
                "description": "Test Transaction",
                "image": "https://yourwebsite.com/logo.png", // Optional logo
                "order_id":  response.razorpay_payment_id,
                "method": "upi",
                "upi": {
                    "flow": "intent",  // Enables UPI intent flow
                    "intent_only": true, // Forces UPI apps instead of web-based input
                },
                "handler": function(response) {
                    alert("Payment Successful! Payment ID: " + response.razorpay_payment_id);
                },
                "prefill": {
                    "name": "John Doe",
                    "email": "john@example.com",
                    "contact": "9999999999"
                },
                "theme": {
                    "color": "#3399cc"
                }
            };

            var rzp1 = new Razorpay(options);
            rzp1.open();
        }
    </script>
</body>
</html>
