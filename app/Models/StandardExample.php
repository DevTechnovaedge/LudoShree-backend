<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandardExample extends Model
{
    use HasFactory;

    protected $appends          =   [   'icon_image_url', 
    'featured_image_url', 
    'banner_image_url', 
    'status_label', 
    'status_label_view',
];

    protected $fillable = [
    	'name',
    	'slug',
        'category_ids',
        'number_of_days',
        'number_of_nights',
        'duration',
        'short_description',
        'major_attraction',
        'destination_ids',
        'tag_ids',
        'inclusion',
        'exclusion',
        'itinerary',
        'dynamic_content',
        'faq',
        'icon',
        'featured_image',
        'banner_image',
        'show_on_menu',
        'show_on_home',
        'show_on_footer',
        'is_featured',
        'is_popular',
        'meta_title',
        'meta_description',
    	'status',
    ];

    protected $visible = [
        'id',
    	'name',
    	'slug',
        'category_ids',
        'number_of_days',
        'number_of_nights',
        'duration',
        'short_description',
        'major_attraction',
        'destination_ids',
        'tag_ids',
        'inclusion',
        'exclusion',
        'itinerary',
        'dynamic_content',
        'faq',
        'icon',
        'featured_image',
        'banner_image',
        'show_on_menu',
        'show_on_home',
        'show_on_footer',
        'is_featured',
        'is_popular',
        'meta_title',
        'meta_description',
    	'status',
    ];

    protected $hidden = [
        'deleted_at',
        'updated_at'
    ];

    protected $casts = [
        'number_of_days'        => 'integer',
        'number_of_nights'      => 'integer',
        'show_on_menu'          => 'integer',
        'show_on_home'          => 'integer',
        'show_on_footer'        => 'integer',
        'is_popular'            => 'integer',
        'is_featured'           => 'integer',
        'status'                => 'integer',
        'itinerary'             => 'object',
        'dynamic_content'       => 'object',
        'faq'                   => 'object',
    ];

    # Common Attribute
    public function getIconImageUrlAttribute(){
        return $this->icon != '' ? asset('storage/tour_packages/icons/'.$this->icon) : asset('assets/img/placeholder-image.png');
    }

    public function getFeaturedImageUrlAttribute(){
        return $this->featured_image != '' ? asset('storage/tour_packages/featured_images/'.$this->featured_image) : asset('assets/img/placeholder-image.png');
    }

    public function getBannerImageUrlAttribute(){
        return $this->banner_image != '' ? asset('storage/tour_packages/banner_images/'.$this->banner_image) : '';
    }

    public function getStatusLabelAttribute(){
        return $this->status ? 'Active' : 'Deactive';
    }

    public function getStatusLabelViewAttribute(){
        return $this->status ? '<span class="btn btn-success btn-sm">Active</span>' : '<span class="btn btn-danger btn-sm">Deactive</div>';
    }
    # End Common Attribute
}
