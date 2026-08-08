<?php

namespace App\Models\Financial;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferCashback extends Model
{
    use HasFactory;
    protected  $table = 'transfer_cashbacks';
    
    protected  $fillable =   [
                                'user_id',
                                'cashback_percentage',
                                'cashback_amount',
                                'actual_win_amount',
                                'transferred_win_amount',
                                'remaining_win_amount',
                                'actual_game_amount',
                                'game_amount_without_cashback',
                                'game_amount_with_cashback',
                            ];      
    
    public function getCreatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

    public function getUpdatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

      #
      public function getCashbackAmountAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }

    public function getActualWinAmountAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }
    
    public function getTransferredWinAmountAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }

    public function getRemainingWinAmountAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }

    public function getActualGameAmountAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }

    public function getGameAmountWithoutCashbackAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }

    public function getGameAmountWithCashbackAttribute($val){
        return number_format($val ?? 0, 2, '.', '');
    }
    #
    
    public  function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->select('id', 'uid', 'name', 'profile', 'game_wallet_amount', 'win_wallet_amount', 'fcm_device_token');
    }
   
}