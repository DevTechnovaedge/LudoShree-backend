<?php

namespace App\Models\GameChallenge;

use App\Models\GatewayPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $appends          =   [
                                        'deposit_screenshot_url',
                                        'status_label',
                                        'status_view',
                                    ];

    protected $fillable = [
        'txn_id',
        'user_id',
        'transfer_type',
        'amount',
        'txn_fee',
        'final_amount',
        'payment_info',
        'remark',
        'deposit_screenshot',
        'total_balance',
        'status',
    ];

    public  function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->select('id', 'uid', 'name', 'profile', 'game_wallet_amount', 'win_wallet_amount', 'fcm_device_token');
    }

    public function getCreatedAtAttribute($val)
    {
        return $val ? date('d F Y h:i a', strtotime($val)) : '';
    }

    public function getStatusLabelAttribute()
    {
        $status_lable           =   '';

        if ($this->status == 0) :
            $status_lable           =   'Pending';
        elseif ($this->status == 1) :
            $status_lable           =   'Success';
        elseif ($this->status == 2) :
            $status_lable           =   'Fail';
        elseif ($this->status == 3) :
            $status_lable           =   'Rejected';
        elseif ($this->status == 4) :
            $status_lable           =   'Expired';
        elseif ($this->status == 5) :
            $status_lable           =   'Cancelled';
        endif;

        return $status_lable;
    }

    public function getStatusViewAttribute()
    {
        $status           =   '';

        if ($this->status == 0) :
            $status           =   '<span class="btn btn-warning btn-sm">Pending</span>';
        elseif ($this->status == 1) :
            $status           =   '<span class="btn btn-success btn-sm">Transfered</span>';
        elseif ($this->status == 2) :
            $status           =   '<span class="btn btn-danger btn-sm">Fail</span>';
        elseif ($this->status == 3) :
                $status           =   '<span class="btn btn-danger btn-sm">Rejected</span>';
        elseif ($this->status == 4) :
                $status           =   '<span class="btn btn-danger btn-sm">Expired</span>';
        elseif ($this->status == 5) :
                $status           =   '<span class="btn btn-danger btn-sm">Cancelled</span>';
        endif;

        return $status;
    }

    public function getDepositScreenshotUrlAttribute(){
        return $this->deposit_screenshot ? asset("storage/proof/deposit-request/$this->txn_id/".$this->deposit_screenshot) : '#';
    }

    # Wallet
    public function wallet(){
        return $this->belongsTo(Wallet::class, 'id');
    }

    public function gatewayPayment()
    {
        return $this->hasOne(GatewayPayment::class, 'transaction_id');
    }

}
