<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SliderImage extends Model
{
    use HasFactory;

    protected $appends      =   ['image_url', 'mobile_image_url', 'status_view'];
 
    protected $fillable = [
        'slider_id',
        'image',
      	'mobile_image',
        'class',
        'heading',
        'description',
        'btn_text',
        'btn_link',
        'video_url',
        'sort_order',
        'slider_url',
        'deep_link',
        'status',
        'open_url_for_android_app',
        'slider_for'
    ];
    
    public function scopeActive($query){
        return $query->where('status', 1);
    }
    
    public function getImageUrlAttribute(){
      	return asset('storage/slider/'.( request()->segment(1) === 'api' ? $this->mobile_image : $this->image));
    }

    public function getMobileImageUrlAttribute(){
      	return asset('storage/slider/'.$this->mobile_image);
    }

    
  
  	public function getStatusViewAttribute(){
        return $this->status ? "<div class='btn btn-sm btn-success'>Active</div>" : "<div class='btn btn-sm btn-danger'>Deactive</div>";
    }
}
