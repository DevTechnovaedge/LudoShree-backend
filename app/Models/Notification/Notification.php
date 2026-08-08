<?php

namespace App\Models\Notification;

use App\Models\Notification\Category;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    use HasFactory;

    protected $table            = 'notifications';

    protected $appends          =   [
        'status_label',
        'status_view',
    ];

    protected $fillable = [
        'title',
        'content',
        'sent_type',
        'schedule_date_time',
        'regular_time',
        'user_ids',
        'notification_type',
        'sent_count'
    ];

    public function scopeSent($query)
    {
        $query->whereIsSent(1);
    }

    public function getUserIdsAttribute($val){
        return $val == 'all' ? 'all' : explode(',', $val);
    }

    public function getStatusLabelAttribute()
    {
        if($this->is_sent == 1){
            return 'Sent';
        }
        elseif($this->is_sent == 2){
            return 'Failed';
        }else{
            return 'Pending';
        }
    }

    public function getStatusViewAttribute()
    {

        if($this->is_sent == 1){
            return '<span class="btn btn-success btn-sm">Sent</span>';
        }
        elseif($this->is_sent == 2){
            return '<span class="btn btn-danger btn-sm">Failed</div>';
        }else{
            return '<span class="btn btn-warning btn-sm">Pending</div>';
        }
    }

    # 

    public function getUserIds($val){
        return $val ? explode(',', $val) : [];
    }
    
    public function getCreatedAt($formattedDate = false){
        return $formattedDate ? date('F m, Y h:i a', strtotime($this->created_at)) : $this->created_at;
    }
    
    public function getPublishedFromAttribute($val){
        return $val ? date('d M, Y', strtotime($val)) : '';
    }
    
     # Relationship
     public function category(){
        return $this->belongsTo(Category::class, 'category_id');
    }
    # End Relationship

}
