<?php

namespace App\Models\GameChallenge;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GameChallenge extends Model
{
    use HasFactory, SoftDeletes;

    protected $with             = ['game_type', 'challenger', 'opponent'];

    protected $appends          =   [
        'challenger_details',
        'game_details',
        'opponent_details',
        'status_label',
        'status_view',

        'challenger_screenshot_url',
        'opponent_screenshot_url',
    ];

    protected $fillable   = [
                                'uid',
                                'game_type_id',
                                'challenger_id',
                                'opponent_id',
                                'roomcode',
                                'roomcode_datetime',
                                'amount',
                                'game_commission',
                                'game_commission_amount',
                                'paid_amount',
                                
                                'closed_at',
                                'status',
    
                                'challenger_amount',
                                'opponent_amount',
                                
                                'penalty',
                                'total_penalty_amount',
                                'deducted_penalty_amount',
                                'remaining_penalty_amount',
                                'penalty_status',

                                'challenger_status',
                                'challenger_remark',
                                'challenger_screenshot',
                                
                                'challenger_result_date',
                                'opponent_result_date',

                                'opponent_status',
                                'opponent_remark',
                                'opponent_screenshot',
                                'opponent_date',

                                'ludo_king_game_id',
                                'ludo_king_result_details',

                                'game_source',
                                'king_table_id',
                                'king_sync_status',

                                'is_lock'
                            ];

    /**
     * Linked to a table on the Daddy King network (either pushed by us or
     * originated remotely).
     */
    public function isKingLinked(): bool
    {
        return ! empty($this->king_table_id) || $this->game_source === 'daddy_king';
    }

    /**
     * Stake one player must pay to join / create (entry fee, not the pot).
     *
     * After accept, `amount` becomes pot (2x entry). Waiting tables should keep
     * entry in both fields, but some rows can drift — always prefer
     * challenger_amount when it is a sensible entry fee.
     */
    public function entryStakeAmount(): float
    {
        $entry = round((float) ($this->challenger_amount ?? 0), 2);
        $amount = round((float) ($this->amount ?? 0), 2);

        if ($entry > 0) {
            // amount already looks like pot (≈ 2 × entry)
            if ($amount > 0 && abs($amount - ($entry * 2)) < 0.02) {
                return $entry;
            }

            // Prefer the smaller positive value when both look like stakes
            if ($amount > 0 && $amount < $entry) {
                return $amount;
            }

            return $entry;
        }

        return max(0.0, $amount);
    }

    /**
     * Compact admin badge: DK Sync (we created) vs DK Remote (Daddy King origin).
     */
    public function kingBadgeHtml(): string
    {
        if (! $this->isKingLinked()) {
            return '';
        }

        $isRemote = $this->game_source === 'daddy_king';
        $variant = $isRemote ? 'is-remote' : 'is-sync';
        $label = $isRemote ? 'Daddy King' : 'DK Linked';
        $sub = $isRemote ? 'Remote' : 'Synced';
        $tableId = $this->king_table_id ? e((string) $this->king_table_id) : '';
        $title = $tableId !== '' ? " title=\"{$tableId}\"" : '';

        $crown = '<svg class="king-badge-crown" viewBox="0 0 24 24" aria-hidden="true">'
            .'<path d="M3.5 9.5l3.2 2.1L9.8 5.8 12 10l2.2-4.2 3.1 5.8 3.2-2.1L19.2 18H4.8L3.5 9.5z" fill="currentColor"/>'
            .'<rect x="4.5" y="18.5" width="15" height="2.2" rx="1.1" fill="currentColor"/>'
            .'</svg>';

        $html = "<span class=\"king-challenge-badge {$variant}\"{$title}>"
            ."<span class=\"king-badge-icon\">{$crown}</span>"
            ."<span class=\"king-badge-copy\">"
            ."<span class=\"king-badge-title\">{$label}</span>"
            ."<span class=\"king-badge-sub\">{$sub}</span>"
            .'</span></span>';

        if ($tableId !== '') {
            $html .= " <small class=\"king-table-id\">{$tableId}</small>";
        }

        return $html;
    }

    /**
     * Small crown chip for ghost / network players in admin columns.
     */
    public function kingPlayerBadgeHtml(): string
    {
        $crown = '<svg class="king-badge-crown" viewBox="0 0 24 24" aria-hidden="true">'
            .'<path d="M3.5 9.5l3.2 2.1L9.8 5.8 12 10l2.2-4.2 3.1 5.8 3.2-2.1L19.2 18H4.8L3.5 9.5z" fill="currentColor"/>'
            .'<rect x="4.5" y="18.5" width="15" height="2.2" rx="1.1" fill="currentColor"/>'
            .'</svg>';

        return '<span class="king-player-badge" title="Daddy King network player">'
            ."<span class=\"king-badge-icon\">{$crown}</span>"
            .'<span>DK Player</span></span>';
    }

    /**
     * Admin Action (win/cancel/suspend) — same decisions as the app, for any
     * joined match (including Daddy King games that may not have a roomcode yet).
     */
    public function canShowAdminActionButton(): bool
    {
        if (in_array((int) $this->status, [3, 4, 6, 7], true)) {
            return false;
        }

        if ((int) $this->challenger_status === 3 && (int) $this->opponent_status === 3) {
            return false;
        }

        // Waiting open table: delete is enough; Action needs a joined match.
        return (bool) $this->roomcode || (bool) $this->opponent_id;
    }

  
    public function getCreatedAtAttribute($val){
        return $val ? date('d F, Y ( h:i a )', strtotime($val)) : '';
    }

    public function getClosedAtAttribute($val){
        return $val ? date('d F, Y ( h:i a )', strtotime($val)) : '';
    }

    public function getChallengerResultDateAttribute($val){
        return $val ? date('d F, Y ( h:i a )', strtotime($val)) : '';
    }

    public function getOpponentResultDateAttribute($val){
        return $val ? date('d F, Y ( h:i a )', strtotime($val)) : '';
    }

    public function getRoomcodeDatetimeAttribute($val){
        return $val ? date('d F, Y ( h:i a )', strtotime($val)) : '';
    }

    public function getChallengerScreenshotUrlAttribute(){
        if ($this->challenger_screenshot && str_starts_with($this->challenger_screenshot, 'http')) {
            return $this->challenger_screenshot;
        }

        if($this->challenger_status == 3 && $this->challenger_screenshot):
            return asset("storage/proof/cancel/$this->uid/$this->challenger_screenshot");
        else:
                return $this->challenger_screenshot ? asset("storage/proof/winner/$this->uid/$this->challenger_screenshot") : "#";
        endif;
    }

    public function getOpponentScreenshotUrlAttribute(){
        if ($this->opponent_screenshot && str_starts_with($this->opponent_screenshot, 'http')) {
            return $this->opponent_screenshot;
        }

        if($this->opponent_status == 3 && $this->opponent_screenshot):
            return asset("storage/proof/cancel/$this->uid/$this->opponent_screenshot");
        else:
            return $this->opponent_screenshot ? asset("storage/proof/winner/$this->uid/$this->opponent_screenshot") : "#";
        endif;
        
    }

    public function getStatusLabelAttribute()
    {
        $status_label = '';
        // if ($this->status == 0 && $this->challenger_id != $this->challenger->id) :
        //     $status_label = 'Waiting';
        // elseif ($this->status == 0 && $this->challenger_id == ( auth('api')->user()->id ??  0 )) :
        //     $status_label = 'Waiting';
        // elseif ($this->status == 0 && $this->challenger_id == $this->challenger->id) :
        if ($this->status == 0) :
            $status_label = 'Waiting';
        elseif ($this->status == 1 && $this->roomcode == '') :
            $status_label = 'Waiting';
        elseif ($this->status == 1 && $this->roomcode != '') :
            $status_label = 'Running';
        elseif ($this->status == 2) :
            $status_label = 'Cancel';
        elseif ($this->status == 3) :
            $status_label = ((int) $this->challenger_status === 3 && (int) $this->opponent_status === 3)
                ? 'Cancelled'
                : 'Uncomplete';
        elseif ($this->status == 4) :
            $status_label = 'Complete';
        elseif ($this->status == 5) :
            $status_label = 'Dispute';
        elseif ($this->status == 6) :
            $status_label = 'Suspended';
        elseif ($this->status == 7) :
            $status_label = 'Cancelled';
        elseif ($this->status == 8) :
            $status_label = 'Uncomplete';
        endif;
        return $status_label;
    }

    public function getStatusViewAttribute()
    {
        $status_view = '';
        if ($this->status == 0) :
            $status_view = '<span class="btn btn-warning btn-sm text-white">Waiting</span>';
        elseif ($this->status == 1 && $this->roomcode != '') :
            $status_view = '<span class="btn btn-success btn-sm">Accepted</span>';
        elseif ($this->status == 1 && $this->roomcode != '') :
            $status_view = '<span class="btn btn-success btn-sm">Running</span>';
        elseif ($this->status == 2) :
            $status_view = '<span class="btn btn-danger btn-sm">Cancel</span>';
        elseif ($this->status == 3) :
            $status_view = '<span class="btn btn-danger btn-sm">Cancelled</span>';
        elseif ($this->status == 4) :
            $status_view = '<span class="btn btn-success btn-sm">Complete</span>';
        elseif ($this->status == 5) :
            $status_view = '<span class="btn btn-danger btn-sm">Dispute</span>';
        elseif ($this->status == 6) :
            $status_view = '<span class="btn btn-danger btn-sm">Suspended</span>';
        elseif ($this->status == 7) :
            $status_view = '<span class="btn btn-danger btn-sm">Cancelled</span>';
        elseif ($this->status == 8) :
            $status_view = '<span class="btn btn-danger btn-sm">Waiting</span>';
        endif;

        return $status_view;
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('challenger_id', $userId)
                     ->orWhere('opponent_id', $userId);
    }

    /**
     * Accepted / in-play challenge: user cannot create or join another until this ends.
     */
    public function scopeRunningForUser($query, $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('challenger_id', $userId)->orWhere('opponent_id', $userId);
        })
            ->whereNotNull('opponent_id')
            ->whereNotIn('status', [2, 3, 4, 6, 7])
            ->where(function ($q) {
                $q->whereNull('challenger_status')->orWhere('challenger_status', '!=', 3);
            })
            ->where(function ($q) {
                $q->whereNull('opponent_status')->orWhere('opponent_status', '!=', 3);
            });
    }

    /**
     * @deprecated Use runningForUser for create/accept locks.
     */
    public function scopeOpenForUser($query, $userId)
    {
        return $query->runningForUser($userId);
    }
    
    public function scopeForWinnerUser($query, $userId)
    {
        return $query->where(function($sub_query) use ($userId){
                    $sub_query->whereChallengerId($userId);
                    $sub_query->whereChallengerStatus(1);
                    $sub_query->whereOpponentStatus(2);
                })->orWhere(function($sub_query) use ($userId){
                    $sub_query->whereOpponentId($userId);
                    $sub_query->whereOpponentStatus(1);
                    $sub_query->whereChallengerStatus(2);
                });
    }
    
    public function scopeForLoserUser($query, $userId)
    {
          return $query->where(function($sub_query) use ($userId){
                    $sub_query->whereChallengerId($userId);
                    $sub_query->whereChallengerStatus(2);
                })->orWhere(function($sub_query) use ($userId){
                    $sub_query->whereOpponentId($userId);
                    $sub_query->whereOpponentStatus(2);
                });
    }
    
    # Game Type
    public  function game_type(){
        return $this->belongsTo(GameType::class, 'game_type_id', 'id')->select('id', 'name');
    }
    # End Game Type

    # Game Type
    public  function challenger(){
        return $this->belongsTo(User::class, 'challenger_id', 'id')->select('id', 'uid', 'name', 'profile', 'fcm_device_token', 'refer_by', 'refer_income', 'game_wallet_amount', 'win_wallet_amount');
    }

    public  function opponent(){
        return $this->belongsTo(User::class, 'opponent_id', 'id')->select('id', 'uid', 'name', 'profile', 'fcm_device_token', 'refer_by', 'refer_income', 'game_wallet_amount', 'win_wallet_amount');
    }

    # End Game Type

    # Game Details
    public function getGameDetailsAttribute(){

        $game_details    =      "<div class='py-1'>".$this->game_type->name."</div>";
        $game_details    .=     "<div class='py-1'>(GameId: $this->uid)</div>";

        $kingBadge = $this->kingBadgeHtml();
        if ($kingBadge !== '') {
            $game_details .= "<div class='py-1'>{$kingBadge}</div>";
        }

        
            $game_details    .=     "<div class='py-1'>$this->status_view</div>";
        
        $game_details    .=     "<div class='py-1'>Created: $this->created_at</div>";

        if($this->closed_at):
            $game_details    .=     "<div class='py-1'>Closed: $this->closed_at</div>";
        endif;
        
        # Action Button — hide once the match is complete / cancelled.
        if( $this->status != 4 ):

            $action_btn = $this->canShowAdminActionButton()
                ? "<button type='button' class='btn btn-sm btn-danger rounded-0 game-challenge-action-btn' data-game-id='$this->uid'>Action</button>"
                : '';
            $game_details    .=     "<div class='py-1'>
                                        $action_btn
                                        <button type='button' class='btn btn-sm btn-danger rounded-0 game-challenge-delete-btn' data-game-id='$this->uid'>Delete</button>
                                    </div>";

            if(!$this->ludo_king_result_details && $this->ludo_king_game_id):
                $game_details    .=     "<div class='py-1'>
                                            <button type='button' class='btn btn-sm btn-success rounded-0 ludo-king-result-view-btn' data-game-id='$this->uid' data-ludo-king-game-id='$this->ludo_king_game_id'>View Result</button>
                                            <div class='py-2 ludo-shree-result-view'><div>
                                        </div>";
            else:
                $ludo_king_result = json_decode($this->ludo_king_result_details);

                $game_status  = $ludo_king_result->status ?? $ludo_king_result->game_status ?? "";
            
            # Winner Condition 
            $winner_status    = '';
            
            
           

                if (($ludo_king_result->ownerstatus ?? '' ) == 'Won'):
                    $challenger_name        =   $this->challenger->name ?? 'N/A';
                    $winner_status    = "Challenger ( $challenger_name ) is Winner";
                elseif (($ludo_king_result->player1status ?? '' ) == 'Won'):
                        $opponent_name        =   $this->opponent->name ?? 'N/A';
                        $winner_status    = "Opponent ( $opponent_name ) is Winner";
                endif;
            

            if($game_status):
                $game_details  .=  "<div><b>Game Status : $game_status</b></div>
                <div><b class='text-success'>$winner_status</b></div>";
            endif;
    # End Winner Condition
            endif;
        endif;
        # End Action Button

        return $game_details;
    }
    # End Game Details

    # Challenger Details
    public function getChallengerDetailsAttribute(){

        $game_result                =  "<div class='btn btn-warning btn-sm text-white'>Waiting</div>";
        $screenshot_url             =   "";
        $screenshot_view            =   "";
        
        # Cancelled
        if($this->challenger_status == 3):
            $game_result                =  "<div class='btn btn-danger btn-sm'>Cancel</div>";
        endif;

        # Winner
        if($this->challenger_status == 1 || $this->challenger_status == 2):
           
            if($this->challenger_status == 1):
                $game_result        =   "<div class='btn btn-success btn-sm'>Win</div>";
            endif;

            if($this->challenger_status == 2):
                $game_result        =   "<div class='btn btn-danger btn-sm'>Lose</div>";
            endif;


         endif;
        #
            if($this->challenger_screenshot):
                $screenshot_url        =   "<a href='$this->challenger_screenshot_url' target='_blank'>View</a>";
                $screenshot_view    .=     "<small><span class='py-1 text-success'>Screenshot Uploaded ( $screenshot_url )</span></small>";
            endif;
        
        $edit_route             =  url('admin/users/' . $this->challenger->id . '/edit');
         
        $uid                =   $this->challenger->uid;
         
        $game_details    =      "<div class='py-1'>".$this->challenger->name."</div>";
        $game_details    .=     "<div class='py-1'><small>( UID : <a href='$edit_route' target='_blank'>$uid</a> )</small></div>";
        if (is_king_ghost_user($this->challenger_id)) {
            $game_details .= "<div class='py-1'>{$this->kingPlayerBadgeHtml()}</div>";
        }
        $game_details    .=     "<div class='py-1'>$game_result</div>";
        
        if($this->challenger_result_date):
            $game_details    .=     "<div class='py-1 time'>Result Updated At: $this->challenger_result_date</div>";
        endif;

        if(( $this->status != 0 && $this->status != 1 )):
            $game_details    .=     $screenshot_view != '' ? $screenshot_view : "<div class='py-1'>Screenshot not uploaded</div>";
        endif;
        
        # Remark
        if($this->challenger_remark && $this->challenger_status == 3):
            $game_details    .=     "<div class='bg-gray px-2'>Remark : $this->challenger_remark</div>";
        endif;
        # End Remark

        return $game_details;
    }
    # End Challenger Details

    # Opponent Details
    public function getOpponentDetailsAttribute(){

        if(!isset($this->opponent_id)):
            return '';
        endif;

        $game_result                =  "<div class='btn btn-warning btn-sm text-white'>Waiting</div>";
        $screenshot_url             =   "";
        $screenshot_view            =   "";

        # 

        # Cancelled
        if($this->opponent_status == 3):
            $game_result                =  "<div class='btn btn-danger btn-sm'>Cancel</div>";
        endif;

        # Winner
        if($this->opponent_status == 1  || $this->opponent_status == 2):
            
            if($this->opponent_status == 1):
                $game_result        =   "<div class='btn btn-success btn-sm'>Win</div>";
            endif;

            if($this->opponent_status == 2):
                $game_result        =   "<div class='btn btn-danger btn-sm'>Lose</div>";
            endif;

         endif;
        #
        #

            if($this->opponent_screenshot):
            $screenshot_url        =   "<a href='$this->opponent_screenshot_url' target='_blank'>View</a>";
                $screenshot_view    .=     "<small><span class='py-1 text-success'>Screenshot Uploaded ( $screenshot_url )</span></small>";
            endif;
            
            $edit_route             =  url('admin/users/' . $this->opponent->id . '/edit');
            $uid                =   $this->opponent->uid;
            
            $game_details    =      "<div class='py-1'>".$this->opponent->name."</div>";
            $game_details    .=     "<div class='py-1'><small>( UID : <a href='$edit_route' target='_blank'>$uid</a> )</small></div>";
            if (is_king_ghost_user($this->opponent_id)) {
                $game_details .= "<div class='py-1'>{$this->kingPlayerBadgeHtml()}</div>";
            }
            $game_details    .=     "<div class='py-1'>$game_result</div>";
            
            if($this->opponent_result_date):
                $game_details    .=     "<div class='py-1 time'>Result Updated At: $this->opponent_result_date</div>";
            endif;
           
            if($this->status != 0 && $this->status != 1):
                $game_details    .=     $screenshot_view != '' ? $screenshot_view : "<div class='py-1'>Screenshot not uploaded</div>";
            endif;
            
             # Remark
            if($this->opponent_remark && $this->opponent_status == 3):
                $game_details    .=     "<div class='bg-gray px-2'>Remark : $this->opponent_remark</div>";
            endif;
            # End Remark
            
            return $game_details;
    }
    # End Opponent Details
    
# Live Challenges
public function scopeLiveChallenges($query, $userId){
    $query->where(function($query) {
        $query->where(function($sub_query){
            $sub_query->where('status', 0);
        })
        ->orWhere(function($sub_query){
            $sub_query->where('status', 1);
            $sub_query->where('roomcode', '!=', '');
        });
    });
}
# End Live Challenges
}
