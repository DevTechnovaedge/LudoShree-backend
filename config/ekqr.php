<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EKQR / UPI Gateway webhook verification
    |--------------------------------------------------------------------------
    |
    | If empty, webhook accepts all requests (legacy behaviour). When set,
    | at least one of the following must match:
    | - Header X-Webhook-Secret or X-EKQR-Token equal to this value
    | - Form/query field webhook_token equal to this value
    | - Authorization: Bearer {this value}
    |
    | If EKQR documents a signed payload later, extend
    | UpiGatewayController::verifyWebhookRequest() accordingly.
    |
    */
    'webhook_secret' => env('EKQR_WEBHOOK_SECRET'),

];

