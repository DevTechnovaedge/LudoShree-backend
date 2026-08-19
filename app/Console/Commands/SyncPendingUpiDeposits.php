<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\PaymentGateway\UpiGatewayController;
use App\Models\GameChallenge\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncPendingUpiDeposits extends Command
{
    protected $signature = 'upi:sync-pending-deposits';

    protected $description = 'Re-check pending UPI Gateway QR deposits and credit wallets when the provider reports success';

    public function handle(): int
    {
        $txnIds = Transaction::query()
            ->where('transfer_type', 'deposit')
            ->where('status', 0)
            ->where('created_at', '>=', now()->subHours(36))
            ->where(function ($q): void {
                $q->where('payment_info', 'like', 'UPI Gateway%')
                    ->orWhere('payment_info', 'like', '%UPI Gateway%')
                    ->orWhere('payment_info', 'upigateway')
                    ->orWhere('payment_info', 'like', 'upigateway%')
                    ->orWhereHas('gatewayPayment', function ($g): void {
                        $g->where('provider', 'ekqr');
                    });
            })
            ->orderBy('id')
            ->limit(40)
            ->pluck('txn_id');

        if ($txnIds->isEmpty()) {
            return self::SUCCESS;
        }

        $gateway = app(UpiGatewayController::class);
        $synced = 0;

        foreach ($txnIds as $txnId) {
            try {
                $gateway->syncStatus((string) $txnId, 0);
                $synced++;
            } catch (\Throwable $e) {
                Log::error('[UPI Gateway] scheduled pending sync failed', [
                    'client_txn_id' => $txnId,
                    'error'         => $e->getMessage(),
                    'exception'     => $e::class,
                ]);
            }
        }

        Log::info('[UPI Gateway] scheduled pending sync', [
            'attempted' => $synced,
            'queued'    => $txnIds->count(),
        ]);

        return self::SUCCESS;
    }
}
