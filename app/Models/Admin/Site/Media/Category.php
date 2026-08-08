<?php

namespace App\Models\Admin\Site\Media;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $table            = 'site_media_category';

    protected $appends          =   [   
                                        'status_label', 
                                        'status_view',
                                    ];

    protected $fillable = [
        'title',
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
