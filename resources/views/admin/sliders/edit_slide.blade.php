@extends('admin.app')

@section('content')

	<!-- Main content -->
	<section class="content">
		<div class="container">
			<div class="row">
				<!-- left column -->
				<div class="col-md-12">
					<!-- general form elements -->
					<div class="card mt-4">

				<div class="card-header bg-theme">
				<div class="row align-items-center">
							<div class="col-sm-6">
								<h5 class="m-0">Edit Slide</h5>
							</div><!-- /.col -->
							<div class="col-sm-6 text-right">
								<a href="{{ url('admin/sliders/'.request()->segment(3).'/slides') }}" class="btn btn-sm btn-warning">List</a>
							</div><!-- /.col -->
						</div><!-- /.row -->
				</div>
				<div class="card-body px-4">
					  
				  	<!-- form start -->
				  	<form method="post" action="{{ url('admin/sliders/edit_slide_process') }}" autocomplete="off" enctype="multipart/form-data">
				  		@csrf
				  		<input type="hidden" name="id" value="{{ $slider->id }}">
				  		<input type="hidden" name="slider_id" value="{{ $main_slider->id }}">
	
                      	<div class="card-body row">
						  <div class="form-group col-md-4">
				        		<label for="heading">Heading <span class="text-danger">*</span></label>
				        		<input type="text" class="form-control" id="heading" name="heading" placeholder="" value="{{ old('heading',$slider->heading) }}">
				      		</div>

				      		<div class="form-group col-md-4">
				        		<label for="price">Image <small class="text-danger">( Banner Size: 1920 * 1080 )</small></label>
				        		<input type="file" class="form-control" id="image" name="image" accept="image/*">
                                
                              	@if($slider->image && file_exists('uploads/slider/'.$slider->image))
                                	<a href="{{ url('uploads/slider/'.$slider->image) }}" target="_blank">View</a>
                                @endif
				      		</div>
                          	
                          	<!-- Mobile Image -->
                          	<div class="form-group col-md-4">
				        		<label for="mobile_image">App / Mobile Image</label>
				        		<input type="file" class="form-control" id="mobile_image" name="mobile_image" accept="image/*">
                                
                              	@if($slider->mobile_image && file_exists('uploads/slider/'.$slider->mobile_image))
                                	<a href="{{ url('uploads/slider/'.$slider->mobile_image) }}" target="_blank">View</a>
                                @endif
				      		</div>
                          	<!-- End Mobile Image -->

                          	<div class="form-group col-md-6 d-none">
                            	<label for="video_url">Video URL</label>
                            	<input type="text" class="form-control" id="video_url" name="video_url" placeholder="" value="{{ old('video_url',$slider->video_url) }}">
                          	</div>


				      		<div class="form-group col-md-12 d-none">
				        		<label for="description">Description</label>
				        		<textarea type="text" class="form-control" id="description" name="description" placeholder="">{{ old('description',$slider->description) }}</textarea>
				      		</div>

				      		<div class="form-group col-md-6 d-none">
				        		<label for="btn_text">Button Text</label>
				        		<input type="text" class="form-control" id="btn_text" name="btn_text" placeholder="" value="{{ old('btn_text',$slider->btn_text) }}">
				      		</div>

				      		<div class="form-group col-md-6 d-none">
				        		<label for="btn_link">Button Link</label>
				        		<input type="text" class="form-control" id="btn_link" name="btn_link" placeholder="" value="{{ old('btn_link',$slider->btn_link) }}">
				      		</div>

				      		<div class="form-group col-md-6 d-none">
				        		<label for="class">Slide Class</label>
				        		<input type="text" class="form-control" id="class" name="class" placeholder="" value="{{ old('class',$slider->class) }}">
				      		</div>

							  <div class="form-group col-md-6">
				        		<label for="slider_url">Slider Url</label>
				        		<input type="text" class="form-control" id="slider_url" name="slider_url" placeholder="Slider Url" value="{{ $slider->slider_url }}">
				      		</div>
				      

				      		<div class="form-group col-md-2">
				        		<label for="sort_order">Sort Order</label>
				        		<input type="text" class="form-control" id="sort_order" name="sort_order" placeholder="" value="{{ old('sort_order',$slider->sort_order) }}">
				      		</div>
				      
				      		
				      		<div class="form-group col-md-6 d-none">
				        		<label for="deep_link">Deep Link</label>
				        		<input type="text" class="form-control" id="deep_link" name="deep_link" placeholder="Deep Link" value="{{ $slider->deep_link }}">
				      		</div>
				      
				      <div class="form-group col-md-4">
				        <label for="status">Active</label>
                        <select class="form-control" id="status" class="status" name="status">
                            <option value="1" {{ $slider->status == 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ $slider->status == 0 ? 'selected' : '' }}>Deactive</option>
                        </select>
				      </div>
				      
				      <div class="form-group col-md-6 d-none">
				        <label for="open_url_for_android_app">Open Url ( Android App) </label>
                        <select class="form-control" id="open_url_for_android_app" class="open_url_for_android_app" name="open_url_for_android_app">
                            <option value="" selected>Choose</option>
                            <option value="1">Web Browser</option>
                            <option value="2">Webview</option>
                        </select>
				      </div>
				      
				      <div class="form-group col-md-6 d-none">
				        <label for="slider_for">Slider for</label>
                        <select class="form-control" id="slider_for" class="slider_for" name="slider_for">
                            <option value="0" selected>All</option>
                            <option value="1">Web</option>
                            <option value="2">App</option>
                        </select>
				      </div>
					  <div class="col-md-12">
									<div class="text-center">
										<button type="submit" class="btn btn-primary">Submit</button>
									</div>
								</div>
				    </div>
				    
				  </form>
				</div>
				</div>
				<!-- /.card -->
			</div>
    	</div>
	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
<script src="https://cdn.ckeditor.com/4.14.0/standard/ckeditor.js"></script>
<script>
$("#category_ids").select2();
$('.ckeditor').ckeditor();
</script>
@endsection