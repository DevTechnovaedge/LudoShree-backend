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
                    return false;
                }

                $newBalance = (float) $lockedUser->game_wallet_amount + $amount;
                $lockedUser->game_wallet_amount = $newBalance;
                $lockedUser->registration_bonus_pending = false;
                $lockedUser->save();

                Wallet::create([
                    'user_id' => $lockedUser->id,
                    'type' => 'credit',
                    'wallet_type' => 'game',
                    'remark' => self::REMARK,
                    'amount' => $amount,
                    'total_balance' => $newBalance,
                    'status' => 1,
                ]);

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
