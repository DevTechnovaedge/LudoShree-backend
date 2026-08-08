<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletResource extends JsonResource
{
    
    
    public function toArray(Request $request): array
    {
        $transaction_status   = "";
        if($this->transaction_id):

            if($this->status == 0 ): 
                $transaction_status   = "( Pending )";
            elseif($this->status == 1 ): 
                $transaction_status   = "( Success )";
            elseif($this->status == 2 ): 
                if($this->type == 'cashback' || $this->type == 'credit' ):
                    $transaction_status   = "( Rejected )";
                else:
                    $transaction_status   = "( Refunded )";
                endif;
            elseif($this->status == 5 ): 
                $transaction_status   = "( Cancelled )";
            endif;

        endif;

        $win_and_game_total_amount = $this->win_and_game_total_amount ?? 0;
        $win_and_game_total_amount = number_format($win_and_game_total_amount, 2);
        $win_and_game_total_amount = str_replace(',','',$win_and_game_total_amount);

        return [
                'id'                    => $this->id,
                'type'                  => $this->type == 'cashback' ? 'credit' : $this->type,
                'wallet_type'           => $this->wallet_type,
                'remark'                => $this->remark.$transaction_status,
                'amount'                => '₹ '.$this->amount,
                'total_balance'         => '₹ '.$win_and_game_total_amount,
                'status'                => $this->status,
                'status_label'          => $this->status_label,
                'created_at'            => $this->created_at,
        ];
    }
}
