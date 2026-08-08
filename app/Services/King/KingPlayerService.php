<?php

namespace App\Services\King;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Resolves proxy ("ghost") user accounts for Daddy King network players so
 * remote tables can be shown as normal game challenges. Ghost users never
 * hold real money - their stakes/winnings live on their own platform.
 */
class KingPlayerService
{
    /**
     * @param  string  $externalId  King player id, e.g. "2-5" (clientId-userId)
     */
    public function resolveGhostUser(string $externalId, string $fullName): User
    {
        $externalId = trim($externalId);

        $existing = User::query()
            ->withoutGlobalScopes()
            ->where('king_player_id', $externalId)
            ->first();

        $displayName = $this->displayName($fullName);

        if ($existing) {
            // Keep display name fresh (players can rename on their platform).
            if ($existing->name !== $displayName && trim($fullName) !== '') {
                $existing->name = $displayName;
                $existing->saveQuietly();
            }

            return $existing;
        }

        $user = new User();
        $user->uid = 'DK' . strtoupper(Str::random(7));
        $user->name = $displayName;
        $user->mobile = $this->uniqueSyntheticMobile();
        $user->password = Hash::make(Str::random(40));
        $user->status = 1;
        $user->kyc_status = 0;
        $user->withdrawal_status = 0;
        $user->game_wallet_amount = 0;
        $user->win_wallet_amount = 0;
        $user->refer_wallet_amount = 0;
        $user->is_king_player = 1;
        $user->king_player_id = $externalId;
        // Required so the challenger/opponent relations (global scope
        // verified_mobile) can load this account.
        $user->is_mobile_verified = 1;
        $user->mobile_verified_at = now();
        $user->save();

        return $user;
    }

    private function displayName(string $fullName): string
    {
        $name = trim($fullName) !== '' ? trim($fullName) : 'King Player';
        $suffix = (string) config('king.player_name_suffix', ' [DK]');

        return Str::endsWith($name, trim($suffix)) ? $name : $name . $suffix;
    }

    /**
     * Real Indian mobiles never start with 0, so synthetic numbers can never
     * collide with a genuine registration.
     */
    private function uniqueSyntheticMobile(): string
    {
        do {
            $mobile = '0' . str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
            $exists = User::query()->withoutGlobalScopes()->where('mobile', $mobile)->exists();
        } while ($exists);

        return $mobile;
    }
}
