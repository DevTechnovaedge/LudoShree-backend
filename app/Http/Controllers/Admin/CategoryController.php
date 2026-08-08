<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\GalleryImage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

class CategoryController extends Controller
{   
    public function index()
    {

        $categories                 =   Category::latest()->get();

        return view("admin.categories.index", compact("categories"));
    }

       public function create()
    {
        $categories                 =   Category::get();
        return view("admin.categories.single",compact('categories'));
    }

   public function store(Request $request)
    { 
       

        $request->validate([
            'name'                  =>  "required|max:225",
          	'slug'                  =>  "required|max:225|unique:categories,slug,$request->id ?? 0 ",
             'icon'                  =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'featured_image'        =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'banner_image'          =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
          
        ]);

        
        $icon                           =   uploadFile($request, 'icon', 'category/icons');
        $featured_image                 =   uploadFile($request, 'featured_image', 'category/featured_images');
        $banner_image                   =   uploadFile($request, 'banner_image', 'category/banner_images');
       
        $category                       =    Category::updateOrCreate(
                                                                            [
                                                                                'id'                    =>  $request->id
                                                                            ],
                                                                            [
                                                                                'name'                  =>  $request->name,
                                                                                'slug'                  =>  $request->slug,
                                                                                'parent_id'             =>  $request->parent_id,
                                                                                'icon'                  =>  $icon,
                                                                                'featured_image'        =>  $featured_image,
                                                                                'banner_image'          =>  $banner_image,
                                                                                'short_description'     =>  $request->short_description,
                                                                                'long_description'      =>  $request->long_description,
                                                                                'show_on_menu'          =>  $request->show_on_menu,
                                                                                'show_on_home'          =>  $request->show_on_home,
                                                                                'show_on_footer'        =>  $request->show_on_footer,
                                                                                'status'                =>  $request->status,
                                                                                'meta_title'            =>  $request->meta_title,
                                                                                'meta_description'      =>  $request->meta_description,
                                                                              	'is_featured'			=>	$request->is_featured,
                                                                              	'is_popular'			=>	$request->is_popular
                                                                            ]
                                                                        );
   
        # Gallery Images
        $gallery_images                           =   uploadGalleryFiles('gallery_images', 'category/gallery_images');

        foreach($gallery_images as $gallery_image):
            GalleryImage::create([
                                    'type'                  =>  'category',
                                    'parent_id'             =>  $category->id,
                                    'name'                  =>  $gallery_image
                                ]);
        endforeach;
        # End Gallery Images

        if($category):
            $msg                            =   $category->wasRecentlyCreated ? 
                                                        "<div class='alert alert-success'>Record added successfully </div>"
                                                        :
                                                        "<div class='alert alert-success'>Record udpated successfully </div>"
                                                        ;
        else:
            $msg                            =   "<div class='alert alert-danger'>Some error occured</div>";
        endif;

        return redirect()->route('admin::categories.index')->with('back_msg', $msg);
    }

    public function show($id)
    {
        //
    }

    public function edit(Category $category)
    {
        $categories                     =   Category::where('id', '!=', $category->id)->get();

        $gallery_images                 =   GalleryImage::type('category')->whereParentId($category->id)->get();

        return view("admin.categories.single", compact('categories', 'category', 'gallery_images'));
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return back()->with('back_msg', "<div class='alert alert-danger'>Record deleted successfully</div>"); 
    }

}
