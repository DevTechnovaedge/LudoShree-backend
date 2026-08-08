<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\CourseOutcomes\Course;

class ContactEnquiry extends Model
{
    use HasFactory;
   
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'course',
        'address',
        'message',
    ];

    public function getCreatedAtAttribute($val){
        return date('F d, Y', strtotime($val));
    }
   
}
