<?php
namespace App\Models;

use App\Models\GameChallenge\GameChallenge;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use HasFactory;
   
    protected $appends  =   [
                                'logo_url',
                                'fav_icon_url',
                                'apk_file_url',
                                'deposit_scanner_img_url',
                                'all_refer_status_label',
                                'all_refer_status_view',
                                'all_withdrawal_status_label',
                                'all_withdrawal_status_view',

                                'pending_challenges_count',
                                'live_challenges_count',
                                'complete_challenges_count',
                                'uncomplete_challenges_count',
                                'uncomplete_games_count',
                                'cancel_challenges_count',
                                'dispute_games_count',
                            ];

    protected $fillable = [
        'site_name',
        'header_line',
        'top_header_text',
        'footer_text',
        'logo',
        'fav_icon',
        'deposit_scanner_img',
        'upi_id',
        'address',
        'socialLinks',
        'payment_gateway',

        'mobile',
        'email',
        'theme_type',
        
        'whatsapp_support',
        'deposit_issue_support_mobile',
        'game_play_issue_support_mobile',
        'telegram_support',
        'youtube_help_video',
        'penalty_amount',
        'minimum_game_play_amount',
        'maximum_game_play_amount',
        'minimum_deposit_amount',
        'maximum_deposit_amount',
        'minimum_withdrawal_limit',
        'maximum_withdrawal_limit',
        'all_withdrawal_status',
        'all_refer_status',
        'ulta_ludo_status',
        'without_kyc_withdrawal_limit',
        'rules',
        'privacy_policy',
        'important_notification',
        
        'disclaimer',
        'theme',
        'custom_css',
        'custom_js',

        'app_details',
        'apk_file',

        'win_to_game_cashback_percentage',
        'minimum_win_amount',

        'refer_to',

        'cashfree_api_key',
        'cashfree_api_secret',
        'rozarpay_api_key',
        'rozarpay_api_secret',
        'upigateway_api_key',

        /** OTP SMS: `fast2sms` (existing) or `vb_http` (GET URL template). */
        'sms_otp_provider',
        /** Full URL with placeholders `{number}`, `{otp}` or `{var}` (OTP). */
        'sms_vb_api_url_template',
    ];

    # Pending Challenges Count
    public function getPendingChallengesCountAttribute(){
        return GameChallenge::whereStatus(0)
                                ->orWhere(function($query){
                                    $query->whereNull('roomcode');
                                    $query->whereStatus(1);
                                })
                                ->count();
    }
    # End Pending Challenges Count

    # Live Challenges Count
    public function getLiveChallengesCountAttribute(){
        return GameChallenge::whereNotNull('roomcode')->whereStatus(1)->count();
    }
    # End Live Challenges Count

    # Complete Challenges Count
    public function getCompleteChallengesCountAttribute(){
        return GameChallenge::whereStatus(4)->count();
    }
    # End Complete Challenges Count

    # Uncomplete Challenges Count
    public function getUncompleteChallengesCountAttribute(){
        return GameChallenge::whereStatus(3)->orWhere('status', 6)->orWhere('status', 7)->count();
    }
    # End Complete Uncomplete Count

    # Uncomplete Challenges Count
    public function getUncompleteGamesCountAttribute(){
        return GameChallenge::whereStatus(8)->count();
    }
    # End Complete Uncomplete Count
    
    # Cancel Challenges Count
    public function getCancelChallengesCountAttribute(){
        return GameChallenge::whereStatus(2)->where('challenger_id', '!=', 0)->where('opponent_id', '!=', 0)->count();
    }
    # End Cancel Uncomplete Count
    
    # Dispute Challenges Count
    public function getDisputeGamesCountAttribute(){
        return GameChallenge::whereStatus(5)->count();
    }
    # End Dispute Uncomplete Count

    #
    public function getAllWithdrawalStatusLabelAttribute(){
        $all_withdrawal_status_label = '';
        if ($this->all_withdrawal_status == 0) :
            $all_withdrawal_status_label = 'Inactive';
        elseif ($this->all_withdrawal_status == 1) :
            $all_withdrawal_status_label = 'Active';
        endif;
        
        return $all_withdrawal_status_label;
    }

    public function getAllWithdrawalStatusViewAttribute(){
        $all_withdrawal_status_view = '';
        if ($this->all_withdrawal_status == 0) :
            $all_withdrawal_status_view = '<span class="btn btn-danger btn-sm">Deactive</span>';
        elseif ($this->all_withdrawal_status == 1) :
            $all_withdrawal_status_view = '<span class="btn btn-success btn-sm">Active</span>';
        endif;

        return $all_withdrawal_status_view;
    }

    public function getAllReferStatusLabelAttribute(){
        $all_refer_status_label = '';
        if ($this->all_refer_status == 0) :
            $all_refer_status_label = 'Inactive';
        elseif ($this->all_refer_status == 1) :
            $all_refer_status_label = 'Active';
        endif;
        
        return $all_refer_status_label;
    }

    public function getAllReferStatusViewAttribute(){
        $all_refer_status_view = '';
        if ($this->all_refer_status == 0) :
            $all_refer_status_view = '<span class="btn btn-danger btn-sm">Deactive</span>';
        elseif ($this->all_refer_status == 1) :
            $all_refer_status_view = '<span class="btn btn-success btn-sm">Active</span>';
        endif;

        return $all_refer_status_view;
    }
    #

    public function getLogoUrlAttribute(){
        return $this->logo ? asset("storage/site/$this->logo") : '';
   }

    public function getFavIconUrlAttribute(){
          return asset("storage/site/$this->fav_icon");
    }

    public function getDepositScannerImgUrlAttribute(){
          return asset("storage/site/deposit-scanner/$this->deposit_scanner_img");
    }

    public function getApkFileUrlAttribute(){
          return asset("storage/site/apk/$this->apk_file");
    }
  
      protected $casts         = [
                                    'socialLinks'         =>     'object',
                                    'theme'             =>     'object',
                                    'app_details'         =>     'object'
                            ];
}
