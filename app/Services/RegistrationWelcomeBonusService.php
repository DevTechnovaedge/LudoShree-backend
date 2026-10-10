<?php

namespace App\Services;

use App\Models\GameChallenge\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * One-time ₹15 game-wallet credit for newly registered app users.
 */
class RegistrationWelcomeBonusService
{
    public const REMARK = 'Registration Welcome Bonus';

    public const AMOUNT = 15;

    /**
     * Credit welcome bonus only for new registrations (registration_bonus_pending).
     */
    public function grantIfEligible(User $user): bool
    {
        $amount = (float) self::AMOUNT;

        if ($amount <= 0) {
            return false;
        }

        try {
            return (bool) DB::transaction(function () use ($user, $amount) {
                $lockedUser = User::withoutGlobalScope('verified_mobile')
                    ->lockForUpdate()
                    ->find($user->id);

                if (!$lockedUser || !$lockedUser->registration_bonus_pending) {
                    return false;
                }

                $alreadyGranted = Wallet::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('remark', self::REMARK)
                    ->where('type', 'credit')
                    ->where('wallet_type', 'game')
                    ->exists();

                if ($alreadyGranted) {
                    User::withoutGlobalScope('verified_mobile')
                        ->whereKey($lockedUser->id)
                        ->update(['registration_bonus_pending' => false]);

                    return false;
                }

                // Credit through WalletService so the ledger snapshot is the
                // real post-credit total. Saving the user first and leaving
                // win_and_game_total_amount empty made WalletObserver add
                // the ₹15 a second time (history showed 30).
                $credited = app(WalletService::class)->credit((int) $lockedUser->id, 'game', $amount, [
                    'remark' => self::REMARK,
                    'status' => 1,
                ]);

                if (! $credited) {
                    return false;
                }

                User::withoutGlobalScope('verified_mobile')
                    ->whereKey($lockedUser->id)
                    ->update(['registration_bonus_pending' => false]);

                $this->notifyUser($lockedUser, $amount);

                return true;
            });
        } catch (\Throwable $e) {
            Log::error('Registration welcome bonus failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    private function notifyUser(User $user, float $amount): void
    {
        $formatted = number_format($amount, 0);
        $title = 'Welcome bonus credited';
        $body = "₹{$formatted} has been added to your game wallet for joining Ludo Shree.";

        safe_notify(
            $user->fcm_device_token,
            $title,
            $body,
            'credit',
            $user->id,
            ['user_id' => $user->id, 'context' => 'welcome_bonus']
        );
    }
}
