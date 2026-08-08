<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\GalleryImage;
use App\Models\LabPackage;
use App\Models\StandardExample;
use Illuminate\Http\Request;

class StandardExampleController extends Controller
{
    # Global 
    public $index_view                  =   "admin.tour-mangement.tour-packages.index";
    public $create_or_edit_view         =   "admin.tour-mangement.tour-packages.single";
    
    public $index_route                 =   "admin::tour-packages.index";
    
    public $permission_key              =   "tour_packages";
    public $table_name                  =   "tour_packages";
    
    # ==> Model 
    public function eloquentModel(){
        return new StandardExample();
    }
    # ==> !Model 
    
    # End Global 

    public function index()
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);

        $records            =   $this->eloquentModel()->get();

        return view($this->index_view, compact('records'));
    }
    
    public function create()
    {
        $this->authorize('permissions', [$this->permission_key, 'create']);

        $categories             =   Category::get();
       
        return view($this->create_or_edit_view, compact('categories'));
    }
    
    
    public function store()
    {
        $this->authorize('permissions', [$this->permission_key, 'create']);
        $custom_errors                              =   [];

        $back_msg                       =   "";
        $id                             =   request()->id ?? 0;

        request()->validate([
            'name'                  =>  "required|max:225|unique:$this->table_name,name,".request()->id,
            'slug'                  =>  "required|max:225|unique:categories,slug,$id ?? 0 ",
            'icon'                  =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'featured_image'        =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
            'banner_image'          =>  "image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048",
         
        ]);

        # Media
        $icon                           =   uploadFile(request(), 'icon', 'tour_packages/icons');
        $featured_image                 =   uploadFile(request(), 'featured_image', 'tour_packages/featured_images');
        $banner_image                   =   uploadFile(request(), 'banner_image', 'tour_packages/banner_images');
        # End Media

        # Object
            # Itinerary
                $itinerary_arr                  =   [];
                foreach(request()->itinerary ?? [] as $itinerary_key => $itinerary_item):
                    if($itinerary_item['title'] && $itinerary_item['description'] == ""):
                        $custom_errors["itinerary.$itinerary_key.description"] = "Please enter description";
                    endif;
        
                    if($itinerary_item['title'] == "" && $itinerary_item['description']):
                        $custom_errors["itinerary.$itinerary_key.title"] = "Please enter title";
                    endif;
        
        
                    if($itinerary_item['day'] && $itinerary_item['title'] && $itinerary_item['description']):

                    $itinerary_arr[]              =   (object)[
                                                            'day'               => $itinerary_item['day'],
                                                            'title'             => $itinerary_item['title'],
                                                            'description'       => $itinerary_item['description'],
                                                        ];
                    endif;
                endforeach;
            # End Itinerary
            
            # Dynamic Content
                $dynamic_content_arr            =   [];

                foreach(request()->dynamic_content ?? [] as $key => $dynamic_content_item):
                    if($dynamic_content_item['heading'] == "" && $dynamic_content_item['description']):
                        $custom_errors["dynamic_content.$key.heading"]                =   "Please enter heading";
                    endif;
        
                    if($dynamic_content_item['heading'] && $dynamic_content_item['description'] == ""):
                        $custom_errors["dynamic_content.$key.description"]                =    "Please enter description";
                    endif;
        
                    if($dynamic_content_item['heading'] && $dynamic_content_item['description']):
                        
                        $dynamic_content_arr[]              =   (object)[
                                                                'heading'               => $dynamic_content_item['heading'],
                                                                'description'           => $dynamic_content_item['description'],
                                                            ];
                                                        endif;
                    endforeach;
            # End Dynamic Content
           
            # Faq
                $faq_arr            =   [];
                foreach(request()->faq ?? [] as $faq_item):

                    if($faq_item['question'] && $faq_item['answer'] == ""):
                        $custom_errors["faq.$key.answer"] = "Please enter answer";
                    endif;
        
                    if($faq_item['question'] == "" && $faq_item['answer']):
                        $custom_errors["faq.$key.question"] = "Please enter question";
                    endif;
        
                    if($faq_item['question'] && $faq_item['answer']):

                        $faq_arr[]              =   (object)[
                                                                'question'                  => $faq_item['question'],
                                                                'answer'                    => $faq_item['answer'],
                                                            ];
                    endif;
                endforeach;
            # End Faq


        # End Object

        if(count($custom_errors)):
            return back()->withErrors($custom_errors)->withInput();
        endif;

        $tour_package   = $this->eloquentModel()->updateOrCreate(
            [
                'id'                    => request()->id
            ],
            [
                'name'                  => request()->name,
                'slug'                  => request()->slug,
                'icon'                  =>  $icon,
                'featured_image'        =>  $featured_image,
                'banner_image'          =>  $banner_image,
                // 'category_ids'          => explode(',', request()->category_ids),
                'number_of_days'        => request()->number_of_days,
                'number_of_nights'      => request()->number_of_nights,
                'duration'              => request()->duration,
                'short_description'     => request()->short_description,
                'major_attraction'      => request()->major_attraction,
                'is_featured'           => request()->is_featured,
                'is_popular'            => request()->is_popular,
                'inclusion'             => request()->inclusion,
                'exclusion'             => request()->exclusion,
                'itinerary'             => $itinerary_arr,
                'dynamic_content'       => $dynamic_content_arr,
                'faq'                   => $faq_arr,
                'show_on_menu'          => request()->show_on_menu,
                'show_on_home'          => request()->show_on_home,
                'show_on_footer'        => request()->show_on_footer,
                'meta_title'            => request()->meta_title,
                'meta_description'      => request()->meta_description,
                'status'                => request()->status
            ]
        );

        # Gallery Images
        $gallery_images                           =   uploadGalleryFiles('gallery_images', 'tour_packages/gallery_images');

        foreach($gallery_images as $gallery_image):
            GalleryImage::create([
                                    'type'                  =>  'tour_package',
                                    'parent_id'             =>  $tour_package->id,
                                    'name'                  =>  $gallery_image
                                ]);
        endforeach;
        # End Gallery Images

        if($tour_package):
            $back_msg                            =   $tour_package->wasRecentlyCreated ? 
                                                        "<div class='alert alert-success'>Record added successfully </div>"
                                                        :
                                                        "<div class='alert alert-success'>Record udpated successfully </div>"
                                                        ;
        else:
            $back_msg                            =   "<div class='alert alert-danger'>Some error occured</div>";
        endif;

        return redirect()->route($this->index_route)->with('back_msg', "<div class='alert alert-success'>$back_msg</div>");
    }

    public function show($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'view']);
    }

    public function edit($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'edit']);
        
        
        $categories             =   Category::get();

        $record                 =   $this->eloquentModel()->find($id);
        $gallery_images                 =   GalleryImage::type('tour_package')->whereParentId($id)->get();

        return view($this->create_or_edit_view, compact('record', 'categories', 'gallery_images'));
    }
    
    public function update(Request $request, $id)
    {
        
    }
    
    public function destroy($id)
    {
        $this->authorize('permissions', [$this->permission_key, 'delete']);
        $record    = $this->eloquentModel()->find($id);
        $record->delete();
        return back()->with('back_msg', "<div class='alert alert-success'>Record deleted successfully</div>");
   }
}