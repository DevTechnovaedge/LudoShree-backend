<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferCodeRequest extends Model
{
    use HasFactory;
    
    protected $table            =   "refer_code_requests";
    
    protected   $fillable       =   [
                                        'ip_address',
                                        'refer_code'
                                    ];

}