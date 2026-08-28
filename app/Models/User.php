<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Models\GameChallenge\CommissionHistory;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
// use Laravel\Sanctum\HasApiTokenss;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Builder;
use DB;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    protected static function booted()
    {
        static::addGlobalScope('verified_mobile', function (Builder $builder) {
            $builder->where('is_mobile_verified', 1);
        });
    }

    protected $appends          =   [
        'kyc_status_label',
        'kyc_status_view',
        'profile_url',
        'kyc_document_front_url',
        'kyc_document_back_url',
        'refer_count',
        'game_play_count',
        'game_win_count',
        'game_lose_count',
        'status_label',
        'status_view',
        'withdrawal_status_label',
        'withdrawal_status_view',
        'user_details',
        'total_wallet_amount',
        'refer_commission_amount_sum',
        'generated_refer_commission_amount',
        'is_profile_updated'
    ];


    protected $fillable = [
        'uid',
        'password',
        'name',
        'mobile',
        'mobile_verified_at',
        'email',
        'email_verified_at',
        'fcm_device_token',
        'profile',
        'dob',
        'otp',
        'otp_expires_at',
        'commission',
        'refer_income',
        'state_id',
        'refer_by',
        'refer_code',
        'document_type_id',
        'document_id',
        'win_wallet_amount',
        'game_wallet_amount',
        'refer_wallet_amount',
        'kyc_document_front',
        'kyc_document_back',
        'remark',
        'withdrawal_status',
        'kyc_status',
        'status',
        'aadhaar_card_details',
        'is_cashier',
        'registration_bonus_pending',
        'is_king_player',
        'king_player_id',
    ];

    /**
     * Admin DataTables skip expensive accessors (game counts, referral sums)
     * that would otherwise run a query per listed row.
     */
    public static bool $skipAppends = false;

    protected $hidden = [
        'kyc_status_label',
        'kyc_status_view',
        'kyc_document_front_url',
        'kyc_document_back_url',
        'status_label',
        // 'status_view',
        // 'updated_at',
        'refer_count',
        'withdrawal_status_label',
        'withdrawal_status_view',
        'is_mobile_verified',
        'mobile_verified_at',
        'password',
        'remember_token',
    ];

    protected function getArrayableAppends()
    {
        if (static::$skipAppends) {
            return [];
        }

        return parent::getArrayableAppends();
    }

    # Is Profile Completed
    public function getIsProfileUpdatedAttribute(){
        $is_profile_completed     = true;
        
        if($this->name == 'Ludo Shree'):
            $is_profile_completed     = false;
        endif;

        if(!$this->email):
            $is_profile_completed     = false;
        endif;

        // if(!$this->email_verified_at):
        //     $is_profile_completed     = false;
        // endif;

        return $is_profile_completed;
    }

    public function getGeneratedReferCommissionAmountAttribute(){
        return CommissionHistory::whereUserId($this->id)->whereReferBy($this->refer_by)->sum('refer_commission_amount');
    }
    
    public function getUserDetailsAttribute()
    {
        $uid = $this->uid;
        $edit_route                 =  url('admin/users/' . $this->id . '/edit');
        $user_details               =      "<span class='py-1'>" . $this->name . "</span>";
        $user_details               .=  " <small>( UID : <a href='$edit_route' target='_balnk'>$uid</a> )</small>";
        return $user_details;
    }

    public function getCreatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

    public function getUpdatedAtAttribute($val)
    {
        return date('d F Y h:i a', strtotime($val));
    }

    public function getKycStatusLabelAttribute()
    {
        $kyc_status_label = '';
        if ($this->kyc_status == 0) :
            $kyc_status_label = 'Pending';
        elseif ($this->kyc_status == 1) :
            $kyc_status_label = 'Approved';
        elseif ($this->kyc_status == 2) :
            $kyc_status_label = 'Rejected';
        endif;
        return $kyc_status_label;
    }

    public function getKycStatusViewAttribute()
    {
        $kyc_status_view = '';
        if ($this->kyc_status == 0) :
            $kyc_status_view = '<span class="btn btn-warning btn-sm">Pending</span>';
        elseif ($this->kyc_status == 1) :
            $kyc_status_view = '<span class="btn btn-success btn-sm">Approved</span>';
        elseif ($this->kyc_status == 2) :
            $kyc_status_view = '<span class="btn btn-danger btn-sm">Rejected</span>';
        endif;

        return $kyc_status_view;
    }

    public function getProfileUrlAttribute()
    {
        return $this->profile ? asset("storage/profile/$this->uid/$this->profile") : asset('assets/images/user.jpg');
    }

    public function getKycDocumentFrontUrlAttribute()
    {
        return $this->kyc_document_front ? asset("storage/kyc_document_front/$this->uid/$this->kyc_document_front") : '';
    }

    public function getKycDocumentBackUrlAttribute()
    {
        return $this->kyc_document_back ? asset("storage/kyc_document_back/$this->uid/$this->kyc_document_back") : '';
    }

    public function getTotalWalletAmountAttribute()
    {
        return $this->win_wallet_amount + $this->game_wallet_amount;
    }

    public function getStatusLabelAttribute()
    {
        return $this->status ? 'Active' : 'Deactive';
    }

    public function getStatusViewAttribute()
    {
        return $this->status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</sapn>';
    }
    public function getWithdrawalStatusLabelAttribute()
    {
        return $this->withdrawal_status ? 'Active' : 'Inactive';
    }

    public function getWithdrawalStatusViewAttribute()
    {
        return $this->withdrawal_status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Inactive</sapn>';
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_cashier' => 'integer',
        'registration_bonus_pending' => 'boolean',
    ];

    public function refer_by_user()
    {
        return $this->belongsTo(User::class, 'refer_by', 'id')->select('id', 'name', 'uid');
    }

    public function referredUsers()
    {
        return $this->hasMany(User::class, 'refer_by', 'id');
    }

    public function asChallenger()
    {
        return $this->hasMany(GameChallenge::class, 'challenger_id');
    }

    public function asOpponent()
    {
        return $this->hasMany(GameChallenge::class, 'opponent_id');
    }

    public function refer_users()
    {
        return $this->hasMany(User::class, 'refer_by', 'id')
        ->withSum('refer_commissions', 'refer_commission_amount')
            ->withSum('deposit', 'amount');
    }

    public function deposit()
    {
        // return $this->hasMany(Transaction::class, 'user_id', 'id')->whereTransferType('deposit')->whereStatus(1);
        return $this->hasMany(Transaction::class, 'user_id', 'id')->latest()->whereTransferType('deposit');
    }

    public function withdrawal()
    {
        // return $this->hasMany(Transaction::class, 'user_id', 'id')->whereTransferType('withdrawal')->whereStatus(1);
        return $this->hasMany(Transaction::class, 'user_id', 'id')->latest()->whereTransferType('withdrawal');
    }

    public function scopeActive($query)
    {
        $query->whereStatus(1);
    }
    
    public function scopeZeroBalance($query)
    {
        return $query->whereWinWalletAmount(0)->whereGameWalletAmount(0);
    }

    public function getReferCountAttribute()
    {
        return $this->refer_users()->count();
    }

    public function getGamePlayCountAttribute()
    {
        $game_challenge = GameChallenge::forUser($this->id);
        
         # Filter
         $filter = request()->filter;
         
        switch($filter) {
            case 'daily':
                $game_challenge = $game_challenge->whereDate('created_at', now());
                break;
        
            case 'weekly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
        
            case 'monthly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ]);
                break;
        }
        # End Filter
        
        if($filter):
            $game_challenge->whereStatus(4);
        endif;
        
        return $game_challenge->count();
    }
    # Win Count
    public function getGameWinCountAttribute()
    {
        $game_challenge = GameChallenge::forWinnerUser($this->id);

        # Filter
        $filter = request()->filter;
         
        switch($filter) {
            case 'daily':
                $game_challenge = $game_challenge->whereDate('created_at', now());
                break;
        
            case 'weekly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
        
            case 'monthly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ]);
                break;
        }
        # End Filter
        
        return $game_challenge->count();
    }
    # End Win Count

    public function getAadhaarCardDetailsAttribute($val){
        return $val ? json_decode($val) : null;
    }

    # Lose Count
    public function getGameLoseCountAttribute()
    {
           
        $game_challenge = GameChallenge::forLoserUser($this->id);
        
        # Filter
        $filter = request()->filter;
         
        switch($filter) {
            case 'daily':
                $game_challenge = $game_challenge->whereDate('created_at', now());
                break;
        
            case 'weekly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
                break;
        
            case 'monthly':
                $game_challenge = $game_challenge->whereBetween('created_at', [
                    now()->startOfMonth(),
                    now()->endOfMonth()
                ]);
                break;
        }
        # End Filter
        
        return $game_challenge->count();
    }
    # End Lose Count

    # Refer Commission Count
    public function refer_commissions()
    {
        return $this->hasMany(CommissionHistory::class, 'refer_by', 'id');
    }
    # End Refer Commission Count
    
     public function getReferCommissionAmountSumAttribute()
    {
        return $this->refer_commissions()->sum('refer_commission_amount') ?? 0;
    }

    //  public function getReferWalletAmountAttribute()
    // {
    //     return $this->refer_commission_amount_sum;
    // }

    public function wallet(){
        return $this->hasMany(Wallet::class, 'user_id');
    }

    /**
     * Proxy account mirroring a player from the Daddy King network.
     * Ghost users never hold real money - stakes/payouts are handled on
     * the player's own platform.
     */
    public function isKingGhost(): bool
    {
        return (int) ($this->is_king_player ?? 0) === 1;
    }

}
