<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slider extends Model
{
    use HasFactory;
	
  	protected $appends	=	['status_view'];
   
    protected $fillable = [
        'name',
        'code',
        'class',
        'status',
        'type'
    ];

    public function scopeCode($query, $code){
        return $query->where('code', $code);
    }

    public function slides(){
        return $this->hasMany(SliderImage::class);
    }
  
  	public function getStatusViewAttribute(){
        return $this->status ? "<div class='btn btn-sm btn-success'>Active</div>" : "<div class='btn btn-sm btn-danger'>Deactive</div>";
    }
}
