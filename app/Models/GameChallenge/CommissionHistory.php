<?php

namespace App\Models\GameChallenge;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommissionHistory extends Model
{
    protected  $table = 'commission_history';
    
    use HasFactory;
    
    protected $appends  =   ['final_game_commission'];
    
    protected $fillable = [
                            'user_id',
                            'refer_by',
                            'game_challenge_id',
                            'total_amount',
                            'game_commission',
                            'game_commission_amount',
                            'refer_commission',
                            'refer_commission_amount',
                            'remark',
                            'status',
                            ];

    public function user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function refer_by_user(){
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function getFinalGameCommissionAttribute(){
        if($this->refer_commission_amount):
            $commission_view    =   "";
            $commission_amount  =  ($this->game_commission_amount) - ( $this->refer_commission_amount );
            $commission_view    .= $commission_amount;
            $commission_view    .= "<div><span class='text-success'>Game Commission: $this->game_commission_amount</span></div>";
            $commission_view    .= "<div><span class='text-info'>Refer Commission : $this->refer_commission_amount</span></div>";
            return $commission_view;
        else:
             ( $this->game_commission_amount ?? 0 );
        endif;
        
    }
    
    public function getCreatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

}