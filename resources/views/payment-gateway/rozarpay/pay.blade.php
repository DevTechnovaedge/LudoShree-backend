<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Razorpay Payment</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
</head>

<body>
    <script>
        function pay_old() {

            var options = {
                "key": "{{ site_setting()->rozarpay_api_key }}", // ✅ Replace with your Razorpay Key ID
                "amount": "{{ $amount *  100 }}", // ₹100 in paise
                "currency": "INR",
                "name": "{{ env('APP_NAME') }}",
                "description": "{{ $transactionId }}",
                "image": "{{ site_setting()->logo_url }}", // Optional logo
                "method": "upi", // ✅ Force UPI method
                "upi": {
                    "flow": "intent" // ✅ Enables automatic app redirection
                },
                "handler": function(response) {
                    window.location.href = `{{ url('rozarpay/return') }}?transaction_id={{ $transactionId }}&payment_id=${response.razorpay_payment_id}`;
                },
                "prefill": {
                    "name": "{{ $user->name }}",
                    "email": "{{ $user->email }}",
                    "contact": "{{ $user->mobile }}"
                },
                "theme": {
                    "color": "#3399cc"
                },
                "modal": {
                    "ondismiss": function(res) {
                        window.location.href = `{{ url('rozarpay/return') }}?transaction_id={{ $transactionId }}&status=5`;
                    }
                }
            };

            var rzp1 = new Razorpay(options);
            rzp1.open();
        }

        function pay() {
            fetch("{{ url('rozarpay/create-order') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({
                        amount: "{{ $amount *  100 }}",
                        transaction_id: "{{ $transactionId }}"
                    })
                })
                .then(response => response.json())
                .then(data => {
                    
                    var options = {
                        "key": "{{ site_setting()->rozarpay_api_key }}",
                        "amount": "{{ $amount * 100 }}",
                        "currency": "INR",
                        "name": "{{ env('APP_NAME') }}",
                        "description": "{{ $transactionId }}",
                        "image": "{{ site_setting()->logo_url }}",
                        "upi": {
                            "flow": "intent"
                        },
                        "order_id": data.id, // ✅ Correct Order ID
                        "handler": function(response) {
                            if (response.razorpay_payment_id) {
                                window.location.href = `{{ url('rozarpay/return') }}?transaction_id={{ $transactionId }}&payment_id=${response.razorpay_payment_id}`;
                            } else {
                                window.location.href = `{{ url('rozarpay/return') }}?transaction_id={{ $transactionId }}&status=2`;
                            }
                        },
                        "prefill": {
                            "name": "{{ $user->name }}",
                            "email": "{{ $user->email }}",
                            "contact": "{{ $user->mobile }}"
                        },
                        "theme": {
                            "color": "#3399cc"
                        },
                        "modal": {
                            "ondismiss": function() {
                                window.location.href = `{{ url('rozarpay/return') }}?transaction_id={{ $transactionId }}&status=5`;
                            }
                        }
                    };
                    var rzp1 = new Razorpay(options);
                    rzp1.open();
                })
                .catch(error => console.error('Error:', error));



        }

        pay()
    </script>
</body>

</html>