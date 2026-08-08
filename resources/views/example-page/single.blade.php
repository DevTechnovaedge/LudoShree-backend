@extends('admin.app')

@section('content')
<style>
	span.select2-selection.select2-selection--single {
		height: 37px;
		padding: 6px;
	}

	.select2-container--default .select2-selection--single .select2-selection__arrow {
		top: 5px;
		right: 5px;
	}
</style>


<!-- Main content -->
<section class="content">
	<div class="container">
		<div class="row">
			<div class="col-md-12">


				<div class="card mt-4">
					<div class="card-header bg-theme">
						<div class="row">
							<div class="col-sm-6 text-left align-self-center">
								<h5 class="m-0">Tour Package</h5>
							</div>
							@can('permissions', ['tour_packages', 'create'] ?: [])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route('admin::tour-packages.index') }}">List</a>
							</div>
							@endcan
						</div>
					</div>
					<div class="card-body p-4">
						@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif
						<div class="row">
							<!-- left column -->
							<div class="col-md-12">
								<!-- form start -->
								<form method="post" action="{{ route('admin::tour-packages.store') }}" enctype="multipart/form-data">
									@csrf
									<input type="hidden" name="id" value="{{ $record->id ?? '' }}">
									<div class="row">
										<div class="col-md-12">
											<details open class="my-2" style="padding: 12px; background: aliceblue;">
												<summary>Details</summary>
												<div class="row p-3">

													<!-- Name -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="name">Name <span class="text-danger">*</span></label>
															<input type="text" class="form-control slug" id="name" name="name" placeholder="Enter name" value="{{ old('name', $record->name ?? '') }}" required>
															@error('name') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Name -->

													<!-- Slug -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="price">Slug</label>
															<input type="text" class="form-control set-slug" id="slug" name="slug" placeholder="Enter slug" value="{{ old('slug', $record->slug ?? '') }}">
															@error('slug') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Slug -->

													<!-- Parent Category -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="parent_id">Category</label>
															<br>
															<select type="text" class="form-control select2" id="category_id" name="category_ids">
																<option value="0">Select Category</option>
																@foreach($categories ?? [] as $row)
																<option value="{{ $row->id }}" {{ ( isset($record) && $record->parent_id == $row->id ) ? 'selected' : '' }}>{{ $row->name }} {{ $row->parent_id == 0 ? '( Main )' : '' }}</option>
																@endforeach
															</select>
														</div>
													</div>
													<!-- End Parent Category -->

													<!-- Number of Day's -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="number_of_days">No. of Day's</label>
															<select type="text" class="form-control select2" id="number_of_days" name="number_of_days">
																@foreach(range(1,30) as $day)
																<option value="{{ $day }}" {{ ( $day == ( $record->number_of_days ?? '' ) ? 'selected' : '') }}>{{ $day }}</option>
																@endforeach
															</select>
															@error('slug') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Number of Day's -->

													<!-- Number of Night's -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="number-of-days">No. of Night's</label>
															<select type="text" class="form-control select2" id="number_of_nights" name="number_of_nights">
																@foreach(range(1,30) as $day)
																<option value="{{ $day }}" {{ ( $day == ( $record->number_of_nights ?? '' ) ? 'selected' : '') }}>{{ $day }}</option>
																@endforeach
															</select>
															@error('slug') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Number of Night's -->

													<!-- Duration -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="duration">Duration</label>
															<input type="text" name="duration" class="form-control" id="duration" placeholder="Example - 10 days and 5 night" value="{{ $record->duration ?? '' }}" readonly>
															@error('duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Duration -->

													<!-- Short Description -->
													<div class="col-md-12">
														<div class="form-group">
															<label for="short_description">Short Description</label>
															<textarea name="short_description" class="form-control" placeholder="Enter short description">{{ $record->short_description ?? '' }}</textarea>
															@error('short_description') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Short Description -->

													<!-- Major Attraction -->
													<div class="col-md-12">
														<div class="form-group">
															<label for="major_attraction">Major Attraction</label>
															<textarea name="major_attraction" class="form-control" placeholder="Enter major attraction">{{ $record->major_attraction ?? '' }}</textarea>
															@error('major_attraction') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Major Attraction -->

													<!-- Destinations -->
													<div class="col-md-6">
														<div class="form-group">
															<label for="destination">Destination</label>
															<select type="text" class="form-control select2" id="destination" name="destination">
																<option value="" selected disabled>Choose..</option>
																@foreach(range(1,30) as $destination)
																<option value="{{ $destination }}">{{ $destination }}</option>
																@endforeach
															</select>
															@error('slug') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Destinations -->

													<!-- Tags -->
													<div class="col-md-6">
														<div class="form-group">
															<label for="tags">Tags</label>
															<select type="text" class="form-control select2" id="tags" name="tags">
																<option value="" selected disabled>Choose..</option>
																@foreach(range(1,30) as $tags)
																<option value="{{ $tags }}">{{ $tags }}</option>
																@endforeach
															</select>
															@error('slug') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!--End Tags -->

													<!-- Featured -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="is_featured">Featured</label>
															<select type="text" class="form-control" id="is_featured" name="is_featured">
																<option value="0" {{ ( isset($record) && $record->is_featured == 0 ) ? 'selected' : '' }}>No</option>
																<option value="1" {{ ( isset($record) && $record->is_featured == 1 ) ? 'selected' : '' }}>Yes</option>
															</select>
														</div>
													</div>
													<!-- End Featured -->

													<!-- Popular -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="is_popular">Popular</label>
															<select type="text" class="form-control" id="is_popular" name="is_popular">
																<option value="0" {{ ( isset($record) && $record->is_popular == 0 ) ? 'selected' : '' }}>No</option>
																<option value="1" {{ ( isset($record) && $record->is_popular == 1 ) ? 'selected' : '' }}>Yes</option>
															</select>
														</div>
													</div>
													<!-- End Popular -->

													<!-- Status -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="status">Status</label>
															<select type="text" class="form-control" id="status" name="status">
																<option value="1" {{ ( isset($record) && $record->status == 1 ) ? 'selected' : '' }}>Active</option>
																<option value="0" {{ ( isset($record) && $record->status == 0 ) ? 'selected' : '' }}>Deactive</option>
															</select>
														</div>
													</div>
													<!-- End Status -->

												</div>
											</details>
										</div>

										<div class="col-md-12">
											<details open class="my-2" style="padding: 12px; background: aliceblue;">
												<summary>Media</summary>
												<div class="row p-4">
													<!-- Icon -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="icon">Icon</label>
															<input type="file" class="form-control p-1" id="icon" name="icon" accept="image/*">
															@error('icon') <div class="text-danger">{{ $message }}</div> @enderror
															@if(isset($record) && $record->icon != '')
															<input type="hidden" name="old_icon" value="{{ $record->icon }}">
															<img src="{{ $record->icon_image_url }}" alt="" class="mt-2" width="50px" height="50px">
															@endif
														</div>
													</div>
													<!-- Icon -->

													<!-- Banner Image -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="banner_image">Banner Image</label>
															<input type="file" class="form-control p-1" id="banner_image" name="banner_image" accept="image/*">
															@error('banner_image') <div class="text-danger">{{ $message }}</div> @enderror
															@if(isset($record) && $record->banner_image != '')
															<input type="hidden" name="old_banner_image" value="{{ $record->banner_image }}">
															<img src="{{ $record->banner_image_url }}" alt="" class="mt-2" width="50px" height="50px">
															@endif
														</div>
													</div>
													<!-- End Banner Image -->

													<!-- Featured Image -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="featured_image">Featured Image</label>
															<input type="file" class="form-control p-1" id="featured_image" name="featured_image" accept="image/*">
															@error('featured_image') <div class="text-danger">{{ $message }}</div> @enderror
															@if(isset($record) && $record->featured_image != '')
															<input type="hidden" name="old_featured_image" value="{{ $record->featured_image }}">
															<img src="{{ $record->featured_image_url }}" alt="" class="mt-2" width="50px" height="50px">
															@endif
														</div>
													</div>
													<!-- End Featured Image -->

												</div>
												<!-- Gallery Images -->
												<x-gallery-images :gallery_images="$gallery_images ?? []" />
												<!-- End Gallery Images -->
											</details>
										</div>


										<!-- Itinerary Tab -->
										<div class="col-md-12">
											<details {{ isset($record->id) ? 'open' : '' }} class="my-2" style="padding:12px; background:aliceblue;">
												<summary>Itinerary</summary>
												<!-- Itinerary - Functionality -->
												<div class="row p-4 itinerary-content-container">
													<div class="col-md-12">
														<x-admin.itinerary :items='$record->itinerary ?? []' />

														<div class="col-md-12 text-right">
															<button type="button" class="btn btn-warning btn-sm text-white" onclick="add_more(this, 'itinerary' ,'.itinerary-content-container')">Add More</button>
														</div>
													</div>
												</div>
												<!-- End  Itinerary - Functionality -->
											</details>
										</div>
										<!-- End Itinerary Tab -->

										<!-- Inclusion and Exclusion Tab -->
										<div class="col-md-12">
											<details {{ isset($record->id) ? 'open' : '' }} class="my-2" style="padding:12px; background:aliceblue;">
												<summary>Inclusion and Exclusion</summary>
												<!-- Inclusion and Exclusion - Functionality -->
												<div class="row p-4 dynamic-content-container">
													<div class="col-md-12">
														<label>Inclusion</label>
														<textarea name="inclusion" class="form-control ckeditor" placeholder="Enter Inclusion">
														{{ $record->inclusion ?? '' }}
														</textarea>
													</div>
													<div class="col-md-12 mt-4">
														<label>Exclusion</label>
														<textarea name="exclusion" class="form-control ckeditor" placeholder="Enter exclusion">
														{{ $record->exclusion ?? '' }}
														</textarea>
													</div>
												</div>
												<!-- End  Inclusion and Exclusion - Functionality -->
											</details>
										</div>
										<!-- End Inclusion and Exclusion Tab -->

										<!-- Dynamic Content Tab -->
										<div class="col-md-12">
											<details {{ isset($record->id) ? 'open' : '' }} class="my-2" style="padding:12px; background:aliceblue;">
												<summary>Dynamic Content</summary>
												<!-- Dynamic Content - Functionality -->
												<div class="row p-4 dynamic-content-container">
													<div class="col-md-12">
														<x-admin.dynamic-contents :dynamicContents='$record->dynamic_content ?? []' />

														<div class="col-md-12 text-right">
															<button type="button" class="btn btn-warning btn-sm text-white" onclick="add_more(this, 'dynamic-content' ,'.dynamic-content-container')">Add More</button>
														</div>
													</div>
												</div>
												<!-- End  Dynamic Content - Functionality -->
											</details>
										</div>
										<!-- End Dynamic Content Tab -->

										<!-- Faq -->
										<div class="col-md-12">
											<details {{ isset($record->id) ? 'open' : '' }} class="my-2" style="padding:12px; background:aliceblue;">
												<summary>Faq</summary>
												<!-- Faq - Functionality -->
												<div class="row p-4 faq-container">
													<div class="col-md-12">

														<x-admin.faqs :items='$record->faq ?? []' />

														<div class="col-md-12 text-right">
															<button type="button" class="btn btn-warning btn-sm text-white" onclick="add_more(this, 'faq' ,'.faq-container')">Add More</button>
														</div>
													</div>
												</div>
												<!-- End Faq - Functionality -->
											</details>
										</div>
										<!-- End Faq -->

										<!-- Additional Tab -->
										<div class="col-md-12">
											<details {{ isset($record->id) ? 'open' : '' }} class="my-2" style="padding:12px; background:aliceblue;">
												<summary>Additional</summary>
												<!-- Additional - Functionality -->
												<div class="row p-4 additional-container">
													<!-- Show on Menu -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="show_on_menu">Show on Menu</label>
															<select type="text" class="form-control" id="show_on_menu" name="show_on_menu">
																<option value="0" {{ ( isset($record) && $record->show_on_menu == 0 ) ? 'selected' : '' }}>No</option>
																<option value="1" {{ ( isset($record) && $record->show_on_menu == 1 ) ? 'selected' : '' }}>Yes</option>
															</select>
														</div>
													</div>
													<!-- End Show on Menu -->

													<!-- Show on Home -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="show_on_home">Show on Home</label>
															<select type="text" class="form-control" id="show_on_home" name="show_on_home">
																<option value="0" {{ ( isset($record) && $record->show_on_home == 0 ) ? 'selected' : '' }}>No</option>
																<option value="1" {{ ( isset($record) && $record->show_on_home == 1 ) ? 'selected' : '' }}>Yes</option>
															</select>
														</div>
													</div>
													<!-- End Show on Home -->

													<!-- Show on Footer -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="show_on_footer">Show on Footer</label>
															<select type="text" class="form-control" id="show_on_footer" name="show_on_footer">
																<option value="0" {{ ( isset($record) && $record->show_on_footer == 0 ) ? 'selected' : '' }}>No</option>
																<option value="1" {{ ( isset($record) && $record->show_on_footer == 1 ) ? 'selected' : '' }}>Yes</option>
															</select>
														</div>
													</div>
													<!-- End Show on Footer -->

												</div>
												<!-- End Additional - Functionality -->
											</details>
										</div>
										<!-- End Additional Tab -->

										<!-- Meta Tab -->
										<div class="col-md-12 ">
											<details {{ isset($record->id) ? 'open' : '' }} style="padding: 12px; background: aliceblue;">
												<summary>Meta</summary>
												<div class="col-md-12 mt-4">
													<div class="form-group">
														<label for="name">Meta Title</label>
														<input type="text" class="form-control" id="meta_title" name="meta_title" placeholder="" value="{{ old('meta_title', $record->meta_title ?? '') }}">
													</div>
												</div>

												<div class="col-md-12">
													<div class="form-group">
														<label for="name">Meta Description</label>
														<textarea type="text" class="form-control" id="meta_description" name="meta_description" placeholder="">{{ old('meta_description', $record->meta_description ?? '') }}</textarea>
													</div>
												</div>
											</details>
										</div>
										<!-- End Meta Tab -->

										<div class="col-md-12 mt-4">
											<button type="submit" class="btn-dm-sm btn-dm-primary btn-lg">Submit</button>
										</div>
									</div>
									<!-- /.card-body -->

								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('script')
<script>
	$('#number_of_days, #number_of_nights').on('change', function() {
		var duration = "";
		var number_of_days = $('#number_of_days').val();
		var number_of_nights = $('#number_of_nights').val();

		if (number_of_days && number_of_nights) {
			duration = `${number_of_days} day's and ${number_of_nights} night's`;
		}

		if (number_of_days && !number_of_nights) {
			duration = `${number_of_days} day's `;
		}

		if (!number_of_days && number_of_nights) {
			duration = `${number_of_nights} night's `;
		}

		$('#duration').val(duration);
	});
</script>
@endsection