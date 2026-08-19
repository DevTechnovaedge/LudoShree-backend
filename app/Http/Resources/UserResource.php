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
        return [
                'id'                    => $this->id,
                "uid"                   => $this->uid,
                "name"                  => $this->name,
                "email"                 => $this->email,
                "mobile"                => $this->mobile,
                "win_wallet_amount"     => $this->win_wallet_amount,
                "game_wallet_amount"    => $this->game_wallet_amount,
                "total_wallet_amount"   => $this->total_wallet_amount,
                "fcm_device_token"      => $this->fcm_device_token,
                "refer_code"            => $this->refer_code,
                "profile_url"           => $this->profile_url,
                "game_play_count"       => $this->game_play_count,
                "status"                => $this->status,
                "status_label"          => $this->status_label,

                "game_win_count"        => $this->game_win_count,
                "game_lose_count"        => $this->game_lose_count,
                
                "total_generated_refer_amount" => round((float) (
                    $this->resource->getAttributes()['total_generated_refer_amount']
                    ?? $this->generated_refer_commission_amount
                    ?? 0
                ), 2),
                "kyc_status"            => $this->kyc_status,
                "kyc_status_label"      => $this->kyc_status_label,

                "is_profile_updated"      => $this->is_profile_updated,

                /** Dynamic cashier access: users.is_cashier OR active admin mobile with Cashier role */
                "is_cashier"              => $this->hasCashierAccess() ? 1 : 0,
        ];
    }
}
