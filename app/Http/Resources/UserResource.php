<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\GameChallenge\Wallet;
use App\Models\GameChallenge\GameChallenge;
use App\Models\Administrator;
use App\Models\Role;
use Illuminate\Support\Facades\Cache;

class UserResource extends JsonResource
{
    private function normalizeMobile(string $mobile): string
       {
           $digits = preg_replace('/\D+/', '', $mobile) ?? '';
           if (strlen($digits) > 10) {
               return substr($digits, -10);
           }
           return $digits;
       }

       private function isCashierRoleName(?string $roleName): bool
       {
           $name = strtolower(trim((string) $roleName));
           return $name === 'cashier' || str_contains($name, 'cashier');
       }

       private function hasCashierAccess(): bool
       {
           if ((int) ($this->is_cashier ?? 0) === 1) {
               return true;
           }

           $mobile = $this->normalizeMobile((string) ($this->mobile ?? ''));
           if ($mobile === '') {
               return false;
           }

           $cashierMobiles = Cache::remember('cashier_admin_mobiles', 300, function () {
               $admins = Administrator::query()
                   ->where('status', 1)
                   ->whereNotNull('mobile')
                   ->where('mobile', '!=', '')
                   ->get(['mobile', 'role_id']);

               if ($admins->isEmpty()) {
                   return [];
               }

               $roleNames = Role::query()
                   ->whereIn('id', $admins->pluck('role_id')->filter()->unique()->values())
                   ->pluck('name', 'id');

               return $admins
                   ->filter(function ($admin) use ($roleNames) {
                       $roleName = $roleNames->get($admin->role_id);

                       return $this->isCashierRoleName($roleName);
                   })
                   ->map(fn ($admin) => $this->normalizeMobile((string) $admin->mobile))
                   ->filter()
                   ->unique()
                   ->values()
                   ->all();
           });

           return in_array($mobile, $cashierMobiles, true);
       }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mobileDigits = preg_replace('/\D+/', '', (string) ($this->mobile ?? '')) ?? '';

        return [
                'id'                    => (int) $this->id,
                "uid"                   => (string) ($this->uid ?? ''),
                "name"                  => $this->name !== null ? (string) $this->name : null,
                "email"                 => $this->email !== null ? (string) $this->email : null,
                "mobile"                => $mobileDigits !== '' ? (int) $mobileDigits : 0,
                "win_wallet_amount"     => (float) ($this->win_wallet_amount ?? 0),
                "game_wallet_amount"    => (float) ($this->game_wallet_amount ?? 0),
                "total_wallet_amount"   => (float) ($this->total_wallet_amount ?? 0),
                "fcm_device_token"      => $this->fcm_device_token !== null ? (string) $this->fcm_device_token : null,
                "refer_code"            => $this->refer_code !== null ? (string) $this->refer_code : null,
                "profile_url"           => (string) ($this->profile_url ?? ''),
                "game_play_count"       => (int) ($this->game_play_count ?? 0),
                "status"                => (int) ($this->status ?? 0),
                "status_label"          => (string) ($this->status_label ?? ''),

                "game_win_count"        => $this->game_win_count,
                "game_lose_count"        => $this->game_lose_count,
                
                "total_generated_refer_amount" => round((float) (
                    $this->resource->getAttributes()['total_generated_refer_amount']
                    ?? $this->generated_refer_commission_amount
                    ?? 0
                ), 2),
                "kyc_status"            => (int) ($this->kyc_status ?? 0),
                "kyc_status_label"      => (string) ($this->kyc_status_label ?? ''),

                "is_profile_updated"      => $this->is_profile_updated,

                /** Dynamic cashier access: users.is_cashier OR active admin mobile with Cashier role */
                "is_cashier"              => $this->hasCashierAccess() ? 1 : 0,
        ];
    }
}
