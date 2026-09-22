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
                    elseif($this->challenger_status == 3 && ( !$this->opponent_status || $this->status == 4 ) && $this->opponent_id):
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '1E26E0 ' ;
                         $is_openable                    =   false;                   
                    elseif(($this->challenger_status == 0 && $this->opponent_status == 3 ) || ($this->opponent_status == 1 &&  $this->challenger_status == 0 )):
                                $status_label                   =  'Result Update' ;
                                $bg_status_color_code           =   '008000';           # Green
                                $is_openable                    =   true;
                    elseif($this->status == 7 || ($this->challenger_status == 3  && !$this->opponent_id) || ( $this->opponent_status == 3 && $this->challenger_status == 3 )):
                        $status_label               =  'Cancelled' ;
                        $bg_status_color_code       =  '5f5f5f' ;
                    elseif($this->status == 6):
                        $status_label               =  'Cancelled' ;
                        $bg_status_color_code       =  '5f5f5f' ;
                    else:
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '1E26E0 ' ;
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
                    elseif($this->opponent_status == 3 && ( !$this->challenger_status || $this->status == 4) && $this->opponent_id):
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '1E26E0 ' ;
                         $is_openable                    =   false;
                    elseif(($this->opponent_status == 0 && $this->challenger_status == 3 ) || ($this->opponent_status == 0 &&  $this->challenger_status == 1 )):
                                $status_label                   =  'Result Update' ;
                            $bg_status_color_code           =   '008000';           # Green
                            $is_openable                    =   true;
                    elseif($this->status == 7 || ($this->opponent_status == 3 && !$this->opponent_id) || ( $this->opponent_status == 3 && $this->challenger_status == 3 )):
                        $status_label               =  'Cancelled' ;
                        $bg_status_color_code       =  '5f5f5f' ;
                        elseif($this->status == 6):
                            $status_label               =  'Cancelled' ;
                            $bg_status_color_code       =  '5f5f5f' ;
                        
                    else:
                        $status_label               =  'Review by admin' ;
                        $bg_status_color_code       =  '1E26E0 ' ;
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

        $gameType = $this->game_type && $this->game_type->id
            ? [
                'id' => $this->asInt($this->game_type->id),
                'name' => $this->asNullableString($this->game_type->name),
            ]
            : null;

        return [
            'id' => $this->asInt($this->id),
            'uid' => $this->asString($this->uid),
            'roomcode' => $this->asNullableString($this->roomcode),
            'amount' => $this->asInt($this->amount),
            'challenge_amount' => $this->asInt($this->challenger_amount),
            'paid_amount' => $this->asFloat($this->paid_amount),
            'challenger_id' => $this->asInt($this->challenger_id),
            'opponent_id' => $this->opponent_id !== null && (int) $this->opponent_id > 0
                ? $this->asInt($this->opponent_id)
                : null,
            'status' => $this->asInt($this->status),
            'game_source' => $this->asString($this->game_source, 'local'),
            'king_table_id' => $this->king_table_id !== null ? $this->asInt($this->king_table_id) : null,
            'is_king_linked' => (bool) $this->isKingLinked(),
            'status_label' => $this->asString($status_label),
            'bg_status_color_code' => $this->asString($bg_status_color_code),
            'is_openable' => (bool) $is_openable,
            'game_type' => $gameType,
            'challenger' => $this->playerPayload($this->challenger),
            'opponent' => $this->opponent ? $this->playerPayload($this->opponent) : null,
        ];
    }

    private function playerPayload($user): array
    {
        if (! $user) {
            return [
                'id' => 0,
                'uid' => '',
                'name' => null,
                'profile_url' => null,
            ];
        }

        return [
            'id' => $this->asInt($user->id),
            'uid' => $this->asString($user->uid),
            'name' => $this->asNullableString($user->name),
            'profile_url' => $this->asNullableString($user->profile_url),
        ];
    }

    private function asInt($value, int $fallback = 0): int
    {
        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function asFloat($value, float $fallback = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $fallback;
    }

    private function asString($value, string $fallback = ''): string
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        return (string) $value;
    }

    private function asNullableString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
