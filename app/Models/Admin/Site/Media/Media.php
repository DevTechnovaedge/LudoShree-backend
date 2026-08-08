<?php

namespace App\Models\Admin\Site\Media;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\Site\Media\Category;

class Media extends Model
{
    use HasFactory;

    protected $table            = 'site_media';

    protected $appends          =   [   
                                        'status_label', 
                                        'status_view',
                                    ];

    protected $fillable = [
        'title',
        'category_id',
        'media_file',
        'media_size',
        'media_extension',
        'generated_link',
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

           # Relationship
    public function category(){
        return $this->belongsTo(Category::class);
    }
    # End Relationship
      
}
