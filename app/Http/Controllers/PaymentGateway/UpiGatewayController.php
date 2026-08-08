<?php declare(strict_types=1);

namespace App\Http\Controllers\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Models\GatewayPayment;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\Notification\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UPI Gateway (ekqr.in) Integration
 * Docs: https://api.ekqr.in/api/create_order, /api/check_order_status
 */
class UpiGatewayController extends Controller
{
    private const BASE_URL = 'https://api.ekqr.in/api';

    private function apiKey(): ?string
    {
        return site_setting()->upigateway_api_key;
    }

    private static function maskApiKey(?string $key): string
    {
        if ($key === null || $key === '') {
            return '(empty)';
        }
        $len = strlen($key);
        if ($len <= 8) {
            return str_repeat('*', min($len, 12));
        }

        return substr($key, 0, 4).'…'.substr($key, -4);
    }

    /**
     * Order id returned by create_order (stored in payment_info) — some providers require it on status check.
     */
    private function parsedGatewayOrderId(Transaction $transaction): ?string
    {
        $pi = (string) $transaction->payment_info;
        if (preg_match('/Order\s*ID\s*:\s*([^\s|]+)/i', $pi, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function statusCheckPayloads(string $clientTxnId, string $txnDate, ?string $orderId): array
    {
        $key = $this->apiKey();
        if ($key === null || $key === '') {
            return [];
        }

        $base = [
            'key'           => $key,
            'client_txn_id' => $clientTxnId,
            'txn_date'      => $txnDate,
        ];

        $out = [$base];

        if ($orderId !== null && $orderId !== '') {
            $out[] = array_merge($base, ['order_id' => $orderId]);
            $out[] = array_merge($base, ['id' => $orderId]);
        }

        return $out;
    }

    private function mergeGatewayPaymentInfo(Transaction $t, string $upiTxnId): void
    {
        $upiTxnId = trim($upiTxnId);
        if ($upiTxnId === '') {
            return;
        }

        $existing = trim((string) $t->payment_info);
        if ($existing !== '' && stripos($existing, $upiTxnId) !== false) {
            return;
        }

        if ($existing !== '') {
            $t->payment_info = $existing.' | UTR '.$upiTxnId;

            return;
        }

        $t->payment_info = 'UPI Gateway UTR : '.$upiTxnId;
    }

    /**
     * Create order on UPI Gateway and return its hosted payment_url.
     * Called from ApiController@deposit_request when payment_gateway = 'upigateway'.
     */
    public function createOrder(Transaction $transaction): array
    {
        $key = $this->apiKey();
        if (!$key) {
            return ['status' => false, 'message' => 'UPI Gateway API key not configured'];
        }

        $user = User::find($transaction->user_id);

        $response = Http::acceptJson()->post(self::BASE_URL . '/create_order', [
            'key'             => $key,
            'client_txn_id'   => $transaction->txn_id,
            'amount'          => (string) (int) $transaction->amount,
            'p_info'          => 'Wallet Deposit',
            'customer_name'   => $user->name ?: 'User '.$user->id,
            'customer_email'  => $user->email ?: 'noreply@merifactory.com',
            'customer_mobile' => (string) ($user->mobile ?: '9999999999'),
            // Plain URL only — many gateways append "?client_txn_id=…&txn_id=…".
            // If we pass "?txn_id=…" here they add another "?", breaking the query string.
            'redirect_url'    => url('upigateway/return'),
            'udf1'            => (string) $user->id,
            'udf2'            => (string) $transaction->id,
            'udf3'            => 'wallet_deposit',
        ]);

        $raw = $response->body();
        Log::info('[UPI Gateway] create_order HTTP response', [
            'client_txn_id' => $transaction->txn_id,
            'amount'        => $transaction->amount,
            'http_status'   => $response->status(),
            'redirect_url_registered' => url('upigateway/return'),
            'webhook_expected_at'     => url('upigateway/webhook'),
            'body_excerpt'            => substr($raw, 0, 2000),
        ]);

        $body = $response->json() ?? [];

        if (!($body['status'] ?? false)) {
            Log::warning('[UPI Gateway] create_order rejected by provider', $body);
            return ['status' => false, 'message' => $body['msg'] ?? 'Failed to create order'];
        }

        $paymentUrl = trim((string) (
            data_get($body, 'data.payment_url')
            ?? data_get($body, 'data.url')
            ?? data_get($body, 'data.paymentUrl')
            ?? data_get($body, 'data.link')
            ?? data_get($body, 'result.payment_url')
            ?? data_get($body, 'result.url')
            ?? data_get($body, 'payment_url')
            ?? ''
        ));

        if ($paymentUrl === '') {
            Log::warning('UPI Gateway create_order: success but no payment URL in payload', $body);
            return ['status' => false, 'message' => 'UPI Gateway did not return a payment URL'];
        }

        $orderId = data_get($body, 'data.order_id') ?? data_get($body, 'result.order_id') ?? data_get($body, 'order_id');

        $transaction->payment_info = 'UPI Gateway Order ID : '.($orderId ?? '');
        $transaction->save();

        $this->ensureGatewayPaymentRow($transaction, is_scalar($orderId) ? (string) $orderId : null, 'pending', 'created');

        return [
            'status'       => true,
            'redirect_url' => $paymentUrl,
            'order_id'     => $orderId,
        ];
    }

    /**
     * Webhook (set in UPI Gateway dashboard): POST {absolute-url}/upigateway/webhook
     *
     * Gateway sends application/x-www-form-urlencoded with fields including:
     * client_txn_id, status (success|failure), upi_txn_id, amount, id (order id), udf*, etc.
     * Prefer applying status from this payload; fall back to check_order_status API if ambiguous.
     */
    public function webhook(Request $request)
    {
        if (! $this->verifyWebhookRequest($request)) {
            Log::warning('[UPI Gateway] webhook rejected: verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $request->all();

        $clientRaw = $payload['client_txn_id'] ?? data_get($payload, 'data.client_txn_id');
        $clientTxnId = is_scalar($clientRaw) ? trim((string) $clientRaw) : '';

        Log::info('[UPI Gateway] webhook inbound', [
            'ip'             => $request->ip(),
            'content_type'   => $request->header('Content-Type'),
            'client_txn_id'  => $clientTxnId !== '' ? $clientTxnId : '(missing)',
            'payload_keys'   => array_keys($payload),
            'status_raw'     => isset($payload['status']) ? (string) $payload['status'] : null,
            'provider_order_id' => $payload['id'] ?? null,
            'payload_json'   => substr(json_encode($payload, JSON_UNESCAPED_UNICODE), 0, 3500),
        ]);

        if ($clientTxnId === '') {
            Log::warning('[UPI Gateway] webhook rejected: client_txn_id missing');

            return response()->json(['status' => false, 'message' => 'client_txn_id missing'], 422);
        }

        $transaction = Transaction::whereTxnId($clientTxnId)->first();
        if (!$transaction) {
            Log::warning('[UPI Gateway] webhook: no local transaction row', ['client_txn_id' => $clientTxnId]);

            return response()->json(['status' => false, 'message' => 'transaction not found'], 404);
        }

        if ((int) $transaction->status === 1) {
            Log::info('[UPI Gateway] webhook ignored: already credited', ['client_txn_id' => $clientTxnId]);

            return response()->json(['status' => true]);
        }

        $hookStatus = strtolower(trim((string) ($payload['status'] ?? '')));
        $upiTxnId = trim((string) ($payload['upi_txn_id'] ?? ''));

        $failStatuses = ['failure', 'failed', 'cancelled', 'canceled', 'expired', 'close', 'closed', 'declined'];

        $hasDefinitiveHook = $hookStatus !== ''
            && (
                $hookStatus === 'success'
                || in_array($hookStatus, $failStatuses, true)
            );

        if (in_array($hookStatus, ['scanning', 'pending', 'created', 'processing'], true)) {
            Log::info('[UPI Gateway] webhook non-terminal status; syncing via API', [
                'client_txn_id' => $clientTxnId,
                'hook_status'    => $hookStatus,
            ]);
        }

        $this->touchGatewayPaymentFromWebhook($transaction, $payload, $hookStatus);

        if ($hasDefinitiveHook) {
            Log::info('[UPI Gateway] webhook applying terminal status', [
                'client_txn_id' => $clientTxnId,
                'hook_status'   => $hookStatus,
                'has_utr'       => $upiTxnId !== '',
            ]);
            try {
                $this->finalizeFromGatewayStatus($transaction, $hookStatus, $upiTxnId);
            } catch (\Throwable $e) {
                Log::error('[UPI Gateway] webhook finalize failed', [
                    'client_txn_id' => $clientTxnId,
                    'hook_status'   => $hookStatus,
                    'error'         => $e->getMessage(),
                    'exception'     => $e::class,
                    'trace'         => substr($e->getTraceAsString(), 0, 6000),
                ]);

                return response()->json(['status' => true]);
            }

            return response()->json(['status' => true]);
        }

        Log::info('[UPI Gateway] webhook falling back to check_order_status', ['client_txn_id' => $clientTxnId]);
        $this->syncStatus($clientTxnId);

        return response()->json(['status' => true]);
    }

    /**
     * Strip accidental "?extra=params" when gateways concatenate URLs incorrectly.
     *
     * @return non-empty-string|null
     */
    private function sanitizeReturnTxnParam(?string $v): ?string
    {
        if ($v === null) {
            return null;
        }

        $v = trim($v);
        if ($v === '') {
            return null;
        }

        $q = strpos($v, '?');
        if ($q !== false) {
            $v = trim(substr($v, 0, $q));
        }

        return $v !== '' ? $v : null;
    }

    /**
     * True when value looks like our merchant txn id (e.g. TXN_…), not the gateway's numeric id.
     */
    private function looksLikeMerchantTxnId(string $v): bool
    {
        return str_starts_with($v, 'TXN_');
    }

    /**
     * Resolve merchant txn id from gateway redirect.
     * Prefer client_txn_id before txn_id — duplicate txn_id keys often carry the gateway numeric id last.
     *
     * @return non-empty-string|null
     */
    private function resolveReturnClientTxnId(Request $request): ?string
    {
        foreach (['client_txn_id', 'transaction_id', 'client_ref_id', 'txn_id'] as $key) {
            $raw = $request->input($key);
            if (! is_scalar($raw)) {
                continue;
            }

            $original = trim((string) $raw);
            if ($original === '') {
                continue;
            }

            $v = $this->sanitizeReturnTxnParam($original);
            if ($v === null) {
                continue;
            }

            if ($key === 'txn_id' && ! $this->looksLikeMerchantTxnId($v) && ctype_digit($v)) {
                continue;
            }

            return $v;
        }

        // Raw query: recover client_txn_id=… even if PHP parsed txn_id wrong
        $qs = (string) $request->server('QUERY_STRING', '');
        if ($qs !== '' && preg_match('/(?:^|[?&])client_txn_id=([^&]*)/', $qs, $m)) {
            $decoded = urldecode(str_replace('+', ' ', $m[1]));
            $v = $this->sanitizeReturnTxnParam($decoded);

            return $v;
        }

        return null;
    }

    /**
     * User redirect after payment. Verifies status then shows the existing callback view.
     */
    public function return(Request $request)
    {
        $txnId = $this->resolveReturnClientTxnId($request);

        Log::info('[UPI Gateway] browser return (redirect_url callback)', [
            'resolved_client_txn_id' => $txnId,
            'method'                 => $request->method(),
            'full_url'               => $request->fullUrl(),
            'query'                  => $request->query(),
            'body_keys'              => array_keys($request->request->all()),
        ]);

        $this->syncStatus($txnId);

        $record = $txnId !== null
            ? Transaction::whereTxnId($txnId)->first()
            : null;

        if (!$record && $txnId === null) {
            $orderRaw = $request->input('order_id') ?? $request->input('id');
            $orderId = is_scalar($orderRaw) ? trim((string) $orderRaw) : '';
            if ($orderId !== '') {
                $gp = GatewayPayment::query()
                    ->where('provider', 'ekqr')
                    ->where('gateway_order_id', $orderId)
                    ->orderByDesc('id')
                    ->first();
                if ($gp && $gp->client_txn_id) {
                    $txnId = $gp->client_txn_id;
                    $this->syncStatus($txnId);
                    $record = Transaction::whereTxnId($txnId)->first();
                    Log::info('[UPI Gateway] return resolved transaction via gateway_order_id', [
                        'gateway_order_id' => $orderId,
                        'client_txn_id'    => $txnId,
                    ]);
                }
            }
        }

        if ($record) {
            Log::info('[UPI Gateway] return page render', [
                'client_txn_id' => $record->txn_id,
                'local_status'  => $record->status,
                'payment_info'  => substr((string) $record->payment_info, 0, 300),
            ]);
        }

        // Re-use the existing rozarpay callback view for consistent UX
        return view('payment-gateway.rozarpay.callback', compact('record'));
    }

    /**
     * Dates to try with check_order_status (gateway expects calendar date of txn; IST vs UTC often mismatches).
     *
     * @return list<string>
     */
    private function txnDatesForGatewayCheck(Transaction $transaction): array
    {
        $created = $transaction->created_at instanceof Carbon
            ? $transaction->created_at->copy()
            : Carbon::parse((string) $transaction->created_at);

        $out = [];
        foreach (['Asia/Kolkata', (string) (config('app.timezone') ?: 'UTC'), 'UTC'] as $tz) {
            if (! is_string($tz) || $tz === '') {
                continue;
            }
            try {
                $local = $created->copy()->timezone($tz);
                $out[] = $local->format('d-m-Y');
                $out[] = $local->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
        }

        return array_values(array_unique(array_filter($out)));
    }

    /**
     * Pull latest status from UPI Gateway and update wallet/transaction atomically.
     * Public so the API layer can re-verify a transaction on demand.
     */
    public function syncStatus(?string $clientTxnId): void
    {
        $clientTxnId = $clientTxnId !== null ? trim($clientTxnId) : '';
        if ($clientTxnId === '') {
            Log::debug('[UPI Gateway] syncStatus skipped: empty client_txn_id');

            return;
        }

        $transaction = Transaction::whereTxnId($clientTxnId)->first();
        if (!$transaction || $transaction->status == 1) {
            Log::debug('[UPI Gateway] syncStatus skipped', [
                'client_txn_id' => $clientTxnId,
                'found'         => (bool) $transaction,
                'already_ok'    => $transaction && (int) $transaction->status === 1,
            ]);

            return;
        }

        $key = $this->apiKey();
        if (!$key) {
            Log::warning('[UPI Gateway] syncStatus aborted: API key empty in site settings');

            return;
        }

        $dates = $this->txnDatesForGatewayCheck($transaction);
        $orderId = $this->parsedGatewayOrderId($transaction);

        Log::info('[UPI Gateway] syncStatus start', [
            'client_txn_id'    => $clientTxnId,
            'local_row_id'     => $transaction->id,
            'amount'           => $transaction->amount,
            'parsed_order_id'  => $orderId,
            'txn_dates_try'    => $dates,
            'payment_info_raw' => substr((string) $transaction->payment_info, 0, 240),
            'api_key_masked'   => self::maskApiKey($key),
            'endpoint'         => self::BASE_URL.'/check_order_status',
        ]);

        foreach ($dates as $txnDate) {
            $payloads = $this->statusCheckPayloads($clientTxnId, $txnDate, $orderId);

            foreach ($payloads as $variantIdx => $postBody) {
                $response = Http::acceptJson()->timeout(45)->post(self::BASE_URL.'/check_order_status', $postBody);

                $raw = $response->body();
                $body = $response->json() ?? [];

                Log::info('[UPI Gateway] check_order_status HTTP', [
                    'client_txn_id' => $clientTxnId,
                    'txn_date'      => $txnDate,
                    'variant'       => $variantIdx,
                    'post_fields'   => array_merge(
                        array_diff_key($postBody, ['key' => true]),
                        ['key' => self::maskApiKey($postBody['key'] ?? '')]
                    ),
                    'http_status'   => $response->status(),
                    'api_ok_flag'   => (bool) ($body['status'] ?? false),
                    'api_msg'       => $body['msg'] ?? null,
                    'body_excerpt'  => substr($raw, 0, 2200),
                ]);

                if (!($body['status'] ?? false)) {
                    continue;
                }

                $remoteStatus = strtolower(trim((string) (
                    data_get($body, 'data.status')
                    ?? data_get($body, 'result.status')
                    ?? data_get($body, 'data.txn_status')
                    ?? data_get($body, 'data.payment_status')
                    ?? data_get($body, 'status')
                    ?? ''
                )));
                $upiTxnId = (string) (
                    data_get($body, 'data.upi_txn_id')
                    ?? data_get($body, 'result.upi_txn_id')
                    ?? data_get($body, 'data.utr')
                    ?? ''
                );

                Log::info('[UPI Gateway] check_order_status parsed', [
                    'client_txn_id'  => $clientTxnId,
                    'txn_date'       => $txnDate,
                    'variant'        => $variantIdx,
                    'remote_status'  => $remoteStatus !== '' ? $remoteStatus : '(empty)',
                    'utr_present'    => $upiTxnId !== '',
                ]);

                if (in_array($remoteStatus, ['scanning', 'pending', 'created', 'processing'], true) || $remoteStatus === '') {
                    Log::info('[UPI Gateway] provider still non-terminal or empty status — not crediting wallet yet', [
                        'client_txn_id' => $clientTxnId,
                        'remote_status' => $remoteStatus !== '' ? $remoteStatus : '(empty)',
                    ]);
                }

                try {
                    $this->finalizeFromGatewayStatus($transaction, $remoteStatus, $upiTxnId);
                } catch (\Throwable $e) {
                    Log::error('[UPI Gateway] finalizeFromGatewayStatus failed (after provider OK envelope)', [
                        'client_txn_id' => $clientTxnId,
                        'remote_status' => $remoteStatus,
                        'error'         => $e->getMessage(),
                        'exception'     => $e::class,
                        'trace'         => substr($e->getTraceAsString(), 0, 6000),
                    ]);
                }

                return;
            }
        }

        Log::warning('[UPI Gateway] check_order_status: all date/variant attempts failed or API error', [
            'client_txn_id' => $clientTxnId,
        ]);
    }

    /**
     * Apply gateway outcome (API or webhook). Uses row lock to avoid double credit under concurrent webhook + redirect.
     */
    private function finalizeFromGatewayStatus(Transaction $transaction, string $remoteStatus, string $upiTxnId): void
    {
        DB::transaction(function () use ($transaction, $remoteStatus, $upiTxnId): void {
            $t = Transaction::whereKey($transaction->id)->lockForUpdate()->first();
            if (!$t || (int) $t->status === 1) {
                return;
            }

            $remoteStatus = strtolower(trim($remoteStatus));
            $isSuccess = in_array($remoteStatus, ['success', 'successful', 'paid', 'completed'], true);
            $isFailure = in_array($remoteStatus, [
                'failure', 'failed', 'cancelled', 'canceled', 'expired', 'close', 'closed', 'declined',
            ], true);

            $this->mergeGatewayPaymentInfo($t, $upiTxnId);

            $walletStatus = 0;
            if ($isSuccess) {
                $walletStatus = 1;
                $t->status = 1;

                $user = User::find($t->user_id);
                $user->game_wallet_amount = $user->game_wallet_amount + $t->amount;
                $user->save();

                $this->notifyDeposit($user, $t->amount);
            } elseif ($isFailure) {
                $walletStatus = 2;
                $t->status = 2;
            }

            $t->save();

            Wallet::whereTransactionId($t->id)->update([
                'win_and_game_total_amount' => User::find($t->user_id)->total_wallet_amount,
                'status' => $walletStatus,
                'remark' => 'Deposit Fund Via UPI Gateway (Order id : '.$t->txn_id.')',
            ]);

            Log::info('[UPI Gateway] finalize local transaction', [
                'client_txn_id' => $t->txn_id,
                'remote_status' => $remoteStatus,
                'local_status_after' => $t->status,
                'wallet_row_status'  => $walletStatus,
            ]);

            $this->syncGatewayPaymentMirrorRow($t, $remoteStatus, $upiTxnId);
        });
    }

    /**
     * Optional shared-secret verification when config('ekqr.webhook_secret') is set.
     */
    private function verifyWebhookRequest(Request $request): bool
    {
        $secret = config('ekqr.webhook_secret');
        if ($secret === null || $secret === '') {
            return true;
        }

        $secret = (string) $secret;

        foreach (['X-Webhook-Secret', 'X-EKQR-Token', 'X-Ekqr-Webhook-Token'] as $header) {
            $h = $request->header($header);
            if (is_string($h) && $h !== '' && hash_equals($secret, $h)) {
                return true;
            }
        }

        $field = $request->input('webhook_token');
        if (is_string($field) && $field !== '' && hash_equals($secret, $field)) {
            return true;
        }

        $auth = (string) $request->header('Authorization', '');
        if ($auth !== '' && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            $bearer = trim($m[1]);
            if ($bearer !== '' && hash_equals($secret, $bearer)) {
                return true;
            }
        }

        return false;
    }

    private function ensureGatewayPaymentRow(Transaction $transaction, ?string $gatewayOrderId, string $normalizedStatus, ?string $rawStatus): void
    {
        try {
            GatewayPayment::updateOrCreate(
                ['client_txn_id' => $transaction->txn_id],
                [
                    'transaction_id'       => $transaction->id,
                    'provider'             => 'ekqr',
                    'gateway_order_id'     => $gatewayOrderId !== null && $gatewayOrderId !== '' ? $gatewayOrderId : null,
                    'amount'               => $transaction->amount,
                    'currency'             => 'INR',
                    'status'               => $normalizedStatus,
                    'gateway_raw_status'   => $rawStatus,
                    'last_payload_excerpt' => null,
                ]
            );
        } catch (\Throwable $e) {
            Log::error('[UPI Gateway] ensureGatewayPaymentRow failed', [
                'client_txn_id' => $transaction->txn_id,
                'error'         => $e->getMessage(),
                'exception'     => $e::class,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function touchGatewayPaymentFromWebhook(Transaction $transaction, array $payload, string $hookStatus): void
    {
        $orderIdRaw = $payload['id'] ?? $payload['order_id'] ?? data_get($payload, 'data.order_id');
        $orderId = is_scalar($orderIdRaw) ? trim((string) $orderIdRaw) : '';
        $upiTxnId = trim((string) ($payload['upi_txn_id'] ?? ''));

        $excerpt = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $excerpt = $excerpt !== false ? substr($excerpt, 0, 800) : null;

        try {
            $row = GatewayPayment::firstOrNew(['client_txn_id' => $transaction->txn_id]);
            $row->transaction_id = $transaction->id;
            $row->provider = 'ekqr';
            $row->amount = $transaction->amount;
            $row->currency = 'INR';
            if ($orderId !== '') {
                $row->gateway_order_id = $orderId;
            }
            $row->status = $this->normalizedGatewayBucket($hookStatus);
            $row->gateway_raw_status = $hookStatus !== '' ? $hookStatus : null;
            if ($upiTxnId !== '') {
                $row->utr = $upiTxnId;
            }
            $row->last_payload_excerpt = $excerpt;
            $row->webhook_received_at = now();
            $row->save();
        } catch (\Throwable $e) {
            Log::error('[UPI Gateway] touchGatewayPaymentFromWebhook failed', [
                'client_txn_id' => $transaction->txn_id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function syncGatewayPaymentMirrorRow(Transaction $transaction, string $remoteStatus, string $upiTxnId): void
    {
        $bucket = $this->normalizedGatewayBucket($remoteStatus);

        try {
            $patch = [
                'transaction_id'       => $transaction->id,
                'provider'             => 'ekqr',
                'amount'               => $transaction->amount,
                'currency'             => 'INR',
                'status'               => $bucket,
                'gateway_raw_status'   => strtolower(trim($remoteStatus)),
            ];

            if ($upiTxnId !== '') {
                $patch['utr'] = $upiTxnId;
            }

            GatewayPayment::updateOrCreate(
                ['client_txn_id' => $transaction->txn_id],
                $patch
            );
        } catch (\Throwable $e) {
            Log::error('[UPI Gateway] syncGatewayPaymentMirrorRow failed', [
                'client_txn_id' => $transaction->txn_id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    private function normalizedGatewayBucket(string $remoteStatus): string
    {
        $r = strtolower(trim($remoteStatus));

        if (in_array($r, ['success', 'successful', 'paid', 'completed'], true)) {
            return 'success';
        }

        if (in_array($r, [
            'failure', 'failed', 'cancelled', 'canceled', 'expired', 'close', 'closed', 'declined',
        ], true)) {
            return 'failed';
        }

        return 'pending';
    }

    private function notifyDeposit(User $user, $amount): void
    {
        $title = 'Amount deposited successfully';
        $body  = 'Amount deposited ( ₹' . $amount . ' ) successfully to game wallet.';

        fcm()->send((object) [
            'title'            => $title,
            'body'             => $body,
            'notification_type'=> 'credit',
            'fcm_device_token' => $user->fcm_device_token,
        ]);

        Notification::create([
            'user_ids'          => $user->id,
            'title'             => $title,
            'content'           => $body,
            'notification_type' => 'credit',
        ]);
    }
}
