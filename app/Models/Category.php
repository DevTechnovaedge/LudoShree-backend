<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $appends          =   [   'icon_image_url', 
                                        'featured_image_url', 
                                        'banner_image_url', 
                                        'status_label', 
                                        'status_label_view',
                                        'has_sub_categories',
                                     	// 'sub_categories' 
                                    ];

    protected $fillable = [
        'name',
        'parent_id',
        'slug',
        'icon',
        'featured_image',
        'banner_image',
        'short_description',
        'long_description',
        'show_on_menu',
        'show_on_home',
        'show_on_footer',
        'status',
        'meta_title',
        'meta_description',
      	'is_featured',
      	'is_popular'
    ];
    
    protected function getNameAttribute($val){
        return str()->title($val);
    }
    
    # Scope
    public function scopeActive($query){
        return $query->where('status', 1);
	}
    
    public function scopeParentCategory($query){
        return $query->where('parent_id', 0);
	}
    # End Scope
    
    # Attributes
        public function getIconImageUrlAttribute(){
            return $this->icon != '' ? asset('storage/category/icons/'.$this->icon) : asset('assets/img/placeholder-image.png');
        }

        public function getFeaturedImageUrlAttribute(){
            return $this->featured_image != '' ? asset('storage/category/featured_images/'.$this->featured_image) : asset('assets/img/placeholder-image.png');
        }

        public function getBannerImageUrlAttribute(){
            return $this->banner_image != '' ? asset('storage/category/banner_images/'.$this->banner_image) : '';
        }

        public function getStatusLabelAttribute(){
            return $this->status ? 'Active' : 'Deactive';
        }

        public function getStatusLabelViewAttribute(){
            return $this->status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</div>';
        }
      
        public function gethasSubCategoriesAttribute(){
            return Category::where('parent_id', $this->id)->count() ?  true : false;
        }
  
        // public function getSubCategoriesAttribute(){
        //     $sub_categories =  array();

        //     // if($this->parent_id == 0):
        //         $sub_categories = Category::select('id', 'name', 'featured_image', 'icon')->where('parent_id', $this->id)->orderBy('name', 'asc')->active()->get();
        //         if($sub_categories):
                    
        //             foreach($sub_categories as $sub_category):
        //                 $level_three_categories = Category::select('id', 'name', 'featured_image', 'icon')->orderBy('name', 'asc')->where('parent_id', $sub_category->id)->active()->get();
                        
        //                 if($level_three_categories):
        //                     $level_three_categories->makeHidden(['featured_image', 'has_sub_categories', 'sub_categories', 'status_label', 'status_label_view', 'sub_categories', 'has_sub_categories']);
        //                     $sub_category->has_level_three_categories = true;
        //                     $sub_category->level_three_categories = $level_three_categories;
        //                 else:
        //                     $sub_category->has_level_three_categories = false;
        //                 endif;

        //             endforeach;
        //             $sub_categories->makeHidden(['featured_image', 'has_sub_categories', 'sub_categories', 'status_label', 'status_label_view', 'sub_categories', 'has_sub_categories']);
        //         endif;
        //     // endif;

        //     return $sub_categories; 

        // }
    # End Attributes

	# Relationships
    public function rel_sub_categories(){
        return $this->hasMany(Category::class, 'parent_id')->whereStatus(1);
    }

	# End Relationships
}
