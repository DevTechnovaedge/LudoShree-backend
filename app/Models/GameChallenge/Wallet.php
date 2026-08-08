<?php

namespace App\Models\GameChallenge;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasFactory;

    protected $table = 'wallet';

    protected $appends      =   ['entry_type_view', 'status_label', 'status_view' ];
    protected $fillable      =   [
                                    'user_id',
                                    'game_challenge_id',
                                    'transaction_id',
                                    'type',
                                    'amount',
                                    'total_balance',
                                    'remark',
                                    'wallet_type',
                                    'added_by',
                                    'status',
                                    'win_and_game_total_amount'
                                ];

    public  function user(){
        return $this->belongsTo(User::class, 'user_id', 'id')->select('id', 'uid', 'name', 'profile', 'mobile');
    }

    public function getCreatedAtAttribute($val){
        return $val ? date('d F, Y h:i a', strtotime($val)) : '';
    }

    public function getUpdatedAtAttribute($val){
        return $val ? date('d F, Y h:i a', strtotime($val)) : '';
    }

    public function getStatusLabelAttribute()
    {
        if($this->status == 0 ): return 'Pending'; endif;
        if($this->status == 1 ): return 'Success'; endif;
        if($this->status == 2 ): return 'Rejected'; endif;
        if($this->status == 5 ): return 'Cancelled'; endif;
    }

    public function getStatusViewAttribute()
    {
        if($this->status == 0 ): 
            return '<span class="btn btn-warning btn-sm">Pending</span>'; 
        endif;

        return $this->status ? '<span class="btn btn-success btn-sm">Success</span>' : '<span class="btn btn-danger btn-sm">Rejected</sapn>';
    }

    public function getEntryTypeViewAttribute(){
        return $this->type == 'credit' ? '<span class="btn btn-success btn-sm">Credit</span>' : '<span class="btn btn-danger btn-sm">Debit</span>';
    }

}