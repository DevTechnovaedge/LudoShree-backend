<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameChallengeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user_id = auth('api')->user()->id;
        
        $opponent                      =   isset($this->opponent) ? 
                                                (object) [
                                                    "id"            => $this->opponent->id ?? null,
                                                    "uid"           => $this->opponent->uid ?? null,
                                                    "name"          => $this->opponent->name ?? null,
                                                    "profile_url"   => $this->opponent->profile_url ?? null,
                                                ] : null ;
             
        
        $is_openable                        =   false;
        $status_label                       =    $this->status_label ?? '';
        $bg_status_color_code               =   'FFFF00';           # Yellow 
        
        # Bg Status Color Code
        
         # Challenger Status
                if( $this->challenger_id == $user_id ):
                    
                     if(( $this->status == 3 || $this->status == 4 ||  $this->status == 5) && ( $this->challenger_status == 0 && $this->opponent_status != 0)):
                            $status_label                   =  'Result Update' ;
                            $bg_status_color_code           =   '008000';           # Green
                            $is_openable                    =   true;
                        endif;
                     
                    if($this->challenger_status == 1 && ( $this->opponent_status == 2 || $this->status == 4 )):
                        $status_label               =  'Winner' ;
                        $bg_status_color_code       =  '008000' ;
                    elseif($this->challenger_status == 2 && ( $this->opponent_status == 1 || $this->status == 4 )):
                       $status_label               =  'Loser' ;
                        $bg_status_color_code       =  'FF0000' ;
                    elseif($this->challenger_status == 3 && ( $this->opponent_status == 3 || !$this->opponent_status || $this->status == 4)):
                        $status_label               =  'Cancelled' ;
                        $bg_status_color_code       =  'FF0000' ;
                    elseif(($this->challenger_status == 0 &&  $this->opponent_status == 3 ) || ($this->challenger_status == 1 &&  $this->opponent_status == 0 )):
                                $status_label                   =  'Result Update' ;
                            $bg_status_color_code           =   '008000';           # Green
                            $is_openable                    =   true;
                    else:
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '87ceeb ' ;
                         $is_openable                    =   false;
                    endif;
                endif;
                # End Challenger Status
                
                # Opponent Status
                if(( $this->opponent_id == auth('api')->user()->id ) ):
                    
                        
                        if(( $this->status == 3 || $this->status == 4 ||  $this->status == 5) && ( $this->challenger_status!= 0 && $this->opponent_status == 0)):
                            $status_label                   =  'Result Update' ;
                            $bg_status_color_code           =   '008000';           # Green
                            $is_openable                    =   true;
                        endif;
                        
                    
                    if($this->opponent_status == 1 && ( $this->challenger_status == 2 || $this->status == 4 )):
                        $status_label               =  'Winner' ;
                        $bg_status_color_code       =  '008000' ;
                    elseif($this->opponent_status == 2 && ( $this->challenger_status == 1 || $this->status == 4 )):
                       $status_label               =  'Loser' ;
                        $bg_status_color_code       =  'FF0000' ;
                    elseif($this->opponent_status == 3 && ( $this->challenger_status == 3 || !$this->challenger_status || $this->status == 4) ):
                        $status_label               =  'Cancelled' ;
                        $bg_status_color_code       =  'FF0000' ;
                         elseif(($this->opponent_status == 0 && $this->challenger_status == 3 )|| ($this->opponent_status == 1 &&  $this->challenger_status == 0 )):
                                $status_label                   =  'Result Update' ;
                            $bg_status_color_code           =   '008000';           # Green
                            $is_openable                    =   true;
                    else:
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '87ceeb ' ;
                         $is_openable                    =   false;
                    endif;
                    
                endif;
                # End Challener Status
        #
        
         if ($this->status == 0 && $this->challenger_id != $user_id) :
             $status_label               =  'Accept' ;
              $bg_status_color_code       =  '0000FF' ;
            endif;
            
        if ($this->status == 0 && $this->challenger_id == $user_id) :
            $status_label               =  'Cancel / Waiting' ;
              $bg_status_color_code       =  'FF0000' ;
        endif;
        
        if ($this->status == 1 && ( $this->opponent_status == 0 ||  $this->challenger_status == 0 )) :
            $status_label               =  'Result Update' ;
            $bg_status_color_code       =  '008000';
            $is_openable                    =   true;
        endif;
        
        if ( $this->status == 1 && $this->opponent_status && !$this->challenger_status  ) :
            $status_label               =  'Result Update' ;
            $bg_status_color_code       =  '008000';
            $is_openable                    =   true;
        endif;
        
        if ( $this->status == 1 && !$this->opponent_status && $this->challenger_status ) :
            $status_label               =  'Result Update' ;
            $bg_status_color_code       =  '008000';
            $is_openable                    =   true;
        endif;
        
        if ($this->status == 1 && ( $this->opponent_id != $user_id &&  $this->challenger_id != $user_id )) :
            $status_label               =  'Running' ;
            $bg_status_color_code       =  'FF0000';
        endif;
        
                                                
        return [
                'id'                        =>  $this->id ?? null,
                 "uid"                      => $this->uid ?? null,
                 "roomcode"                 => $this->roomcode ?? null,
                 "amount"                   => $this->amount ?? null,
                 "challenge_amount"         => ( $this->challenger_amount ) ?? null,
                 "paid_amount"              => $this->paid_amount ?? null,
                 "challenger_id"            => $this->challenger_id ?? null,
                 "opponent_id"              => $this->opponent_id ?? null,
                 "status"                   => $this->status ?? null,
                 "status_label"             => $status_label,
                 "bg_status_color_code"     => $bg_status_color_code,
                 "is_openable"              => $is_openable,
                 "game_type"            => (object) [
                                                        'id'            => $this->game_type->id ?? null,
                                                        'name'          => $this->game_type->name ?? null
                                                    ],
                "challenger"            => (object) [
                                                        "id"            => $this->challenger->id ?? null,
                                                        "uid"           => $this->challenger->uid ?? null,
                                                        "name"          => $this->challenger->name ?? null,
                                                        "profile_url"   => $this->challenger->profile_url ?? null,
                                                    ],
                "opponent"              =>  $opponent
        ];
    }
}
