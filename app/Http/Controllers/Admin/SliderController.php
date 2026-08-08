<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Slider;
use App\Models\SliderImage;
use App\Models\Category;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Str;
use DB;
use Session;


class SliderController extends Controller
{
    public function index()
    {
        $sliders = Slider::get();
      
        return view('admin.sliders.index', compact('sliders'));
    }

    public function create()
    {
        return view("admin.sliders.create");
    }

    public function store(Request $request)
    {
        $inputs = $request->all();

        $request->validate([
            'name' => 'required'
        ]);

        $slider = Slider::create($inputs);

        $back_msg = "";

        if($slider):
            $back_msg = "<div class='alert alert-success'>Slider added successfully</div>";
        else:
            $back_msg = "<div class='alert alert-danger'>Some error occured. Please try again</div>";
        endif;

        return redirect()->to('admin/sliders')->with('back_msg', $back_msg);
    }

    public function show(Slider $slider)
    {
        //
    }

    public function edit(Slider $slider)
    {
        return view("admin.sliders.edit", compact('slider'));
    }

    public function update(Request $request, $id)
    {

            $slider = Slider::findOrFail($id);

            $request->validate([
                'name' => 'required'
            ]);

            $update_array = $request->all();

            $slider = $slider->update($update_array);

       
            if($slider):
                $back_msg = "<div class='alert alert-success'>Slider updated successfully</div>";
            else:
                $back_msg = "<div class='alert alert-danger'>Some error occured. Please try again</div>";
            endif;
    
            return redirect()->to('admin/sliders')->with('back_msg', $back_msg);
    }

    public function destroy($id)
    {
        try {
            $slider = Slider::findOrFail($id);

            $slider->delete();

            $slider_images = SliderImage::select("*")->where('slider_id', $slider->id)->get();
            foreach ($slider_images as $row) {
                $sliderimage = SliderImage::find($row->id);
                if ($sliderimage) {
                    $sliderimage->delete();

                    if ($sliderimage && $sliderimage->image && file_exists('uploads/slider/' . $sliderimage->image)) {
                        unlink('uploads/slider/' . $sliderimage->image);
                    }
                }
            }

            return redirect()->route('admin::sliders.index')->with('success', 'Slider deletd successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->route('admin::sliders.index')->with('error', 'Slider Not Found');
        }
    }

    private function uploadimage($request, $folder, $file)
    {
        $destinationPath = 'uploads/' . $folder;
        $imgName = '';
        //if($request->hasfile('logo')) 
        if ($request->hasfile($file)) {
            $file = $request->file($file);
            $extension = $file->getClientOriginalExtension();
            $filename = time() . rand(10, 100) . '.' . $extension;
            $file->move($destinationPath, $filename);
            $imgName = $filename;
        }
        return $imgName;
    }

    public function slides($id)
    {
        $slider = Slider::where("id", $id)->first();
        if (!$slider) {
            return redirect("/admin");
        }
        $sliders = SliderImage::where("slider_id", $id)->orderBy("sort_order", "asc")->get();
        return view('admin.sliders.slides', compact('slider', 'sliders'));
    }

    public function add_slide($id)
    {
        $slider = Slider::where("id", $id)->first();
        if (!$slider) {
            return redirect("/admin");
        }

        return view('admin.sliders.add_slide', compact('slider'));
    }

    public function add_slide_process(Request $request)
    {
      	
        $main_slider = Slider::where("id", $request->slider_id)->first();

        $inputs = $request->all();
      
        $request->validate([
            'slider_id'     =>  'required',
            'image'         =>  'required|image',
            'heading'       =>  'required'
        ]);
      
        // $image = $this->uploadimage($request, "slider", "image");

        $image                     =   uploadFile('image', 'slider');

        if ($image) {
            $inputs['image'] = ($image) ? $image : '';
        }
      
      	# Mobile Image
      		// $mobile_image 					= 	$this->uploadimage($request, "slider", "mobile_image");
              $mobile_image                     =   uploadFile('mobile_image', 'slider');
      
          if ($mobile_image) {
              $inputs['mobile_image'] = ($mobile_image) ? $mobile_image : '';
          }
      	# End Mobile Image

        $inputs['sort_order'] = ($inputs['sort_order'] && is_numeric($inputs['sort_order'])) ? $inputs['sort_order'] : 0;

        $slider = SliderImage::create($inputs);

        return redirect('admin/sliders/' . $request->slider_id . '/slides')->with("success", "Slide added successfully.");
    }
    public function edit_slide($id, $id2)
    {
        $main_slider = Slider::where("id", $id)->first();
        if (!$main_slider) {
            return redirect("/admin");
        }

        $slider = SliderImage::where("id", $id2)->first();
        if (!$slider) {
            return redirect("/admin");
        }

        return view('admin.sliders.edit_slide', compact('main_slider', 'slider'));
    }

    public function edit_slide_process(Request $request)
    {
      
        $slider		 					= 	SliderImage::where("id", $request->id)->first();
        $inputs 						= 	$request->all();

        $request->validate([
            'slider_id'     =>  'required',
            'heading'       =>  'required'
        ]);

        // $image 							= 	$this->uploadimage($request, "slider", "image");
        $image                     =   uploadFile('image', 'slider');
      
        if ($image) {
            // if ($slider->image && file_exists('uploads/slider/' . $slider->image)) {
            //     unlink('uploads/slider/' . $slider->image);
            // }
            $inputs['image'] = ($image) ? $image : '';
        }
		
      	# Mobile Image
      		// $mobile_image 					= 	$this->uploadimage($request, "slider", "mobile_image");
      
            $mobile_image                     =   uploadFile('mobile_image', 'slider');

           if ($mobile_image) {
            // if ($slider->mobile_image && file_exists('uploads/slider/' . $slider->mobile_image)) {
            //     unlink('uploads/slider/' . $slider->mobile_image);
            // }
            $inputs['mobile_image'] = ($mobile_image) ? $mobile_image : '';
        }
      	# End Mobile Image
      	
      
        $inputs['sort_order'] = ($inputs['sort_order'] && is_numeric($inputs['sort_order'])) ? $inputs['sort_order'] : 0;

        $slider->update($inputs);

        return redirect('admin/sliders/' . $request->slider_id . '/slides')->with("success", "Slide updated successfully.");
    }

    public function deleteSlide($id)
    {
        try {
            $sliderimage = SliderImage::findOrFail($id);

            $sliderimage->delete();

            if ($sliderimage) {
                $sliderimage->delete();

                // if ($sliderimage && $sliderimage->image && file_exists('uploads/slider/' . $sliderimage->image)) {
                //     unlink('uploads/slider/' . $sliderimage->image);
                // }
            }

            return redirect()->back()->with('success', 'Slide deletd successfully.');
        } catch (ModelNotFoundException $e) {
            return redirect()->back()->with('error', 'Slider Not Found');
        }
    }

    public function updateSortOrder(Request $request)
    {
        $slider = Slider::where("id", $request->id)->first();

        $sort_ids = ($request->sort_ids) ? explode(",", $request->sort_ids) : array();

        if ($sort_ids && count($sort_ids) > 0) {
            foreach ($sort_ids as $key => $value) {
                $slider_image = SliderImage::where("id", $value)->first();
                if ($slider_image) {
                    $inputs = array('sort_order' => $key);
                    $slider_image->update($inputs);
                }
            }
        }

        Session::flash('success', 'Updated Successfully.');

        return response()->json(["status" => true, "message" => "Updated Successfully."]);
    }
}
