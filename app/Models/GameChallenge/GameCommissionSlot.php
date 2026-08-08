<?php

namespace App\Models\GameChallenge;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameCommissionSlot extends Model
{
    use HasFactory;

    protected  $table = 'game_commission_slot';
    
    protected $fillable = [
                            'slot_1_to_99',
                            'slab_100_to_499',
                            'slab_500_to_above',
                            'refer_commission'
                        ];


    
}