<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\GameChallenge\Wallet;
use App\Models\GameChallenge\GameChallenge;
use App\Models\Administrator;
use App\Models\Role;

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

           $admins = Administrator::select('mobile', 'role_id', 'status')
               ->where('status', 1)
               ->get();

           foreach ($admins as $admin) {
               if (!$admin->role_id) continue;
               $adminMobile = $this->normalizeMobile((string) ($admin->mobile ?? ''));
               if ($adminMobile !== $mobile) continue;
               $roleName = Role::where('id', $admin->role_id)->value('name');
               if ($this->isCashierRoleName($roleName)) {
                   return true;
               }
           }

           return false;
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
                
                "total_generated_refer_amount"        => $this->generated_refer_commission_amount ?? 0,
                "kyc_status"            => $this->kyc_status,
                "kyc_status_label"      => $this->kyc_status_label,

                "is_profile_updated"      => $this->is_profile_updated,

                /** Dynamic cashier access: users.is_cashier OR active admin mobile with Cashier role */
                "is_cashier"              => $this->hasCashierAccess() ? 1 : 0,
        ];
    }
}
