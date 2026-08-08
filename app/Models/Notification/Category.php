<?php

namespace App\Models\Notification;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use HasFactory;

    protected $table            = 'notification_categories';

    protected $appends          =   [   
                                        'status_label', 
                                        'status_view',
                                    ];

    protected $fillable = [
        'title',
        'slug',
        'status',
       ];

       public function scopeActive($query){
        $query->whereStatus(1);
    }
    
     
        public function getStatusLabelAttribute(){
            return $this->status ? 'Active' : 'Deactive';
        }

        public function getStatusViewAttribute(){
            return $this->status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</div>';
        }
      
}
