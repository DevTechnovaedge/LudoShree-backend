<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cashfree Checkout Integration</title>
        <script src="https://sdk.cashfree.com/js/v3/cashfree.js"></script>
    </head>
    <body>
        <div class="row">
            <button id="renderBtn" style="border:0;"></button>
        </div>
        <script>
            const cashfree = Cashfree({
                // mode: "sandbox",
                mode: "production", 
            });
            document.addEventListener("DOMContentLoaded", () => {
            let checkoutOptions = {
                paymentSessionId: "{{ $paymentSessionId }}",
                redirectTarget: "_self",
            };
            cashfree.checkout(checkoutOptions);
            });
        </script>
    </body>
</html>