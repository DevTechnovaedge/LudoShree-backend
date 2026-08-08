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
								<h5 class="m-0">{{ str()->plural($shared_data->page_title) }}</h5>
							</div>
							@can('permissions', [ $shared_data->permission_key, 'view' ] ?: [])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route($shared_data->index_route) }}">List</a>
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
								<form method="post" action="{{ Route::has($shared_data->store_route) ? route($shared_data->store_route) : '#' }}" enctype="multipart/form-data">
									@csrf
									<input type="hidden" name="id" value="{{ $record->id ?? '' }}">
									<div class="row">
										<div class="col-md-12">
											<div class="row p-3">

												<!-- Course Type -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="course_type_id">Course Type <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 get_course_category_options" id="course_types" name="course_type_id" required>
															<option value="" disabled selected>Choose...</option>

															@foreach (App\Models\Academic\CourseType::get() as $course_type)
															<option value="{{ $course_type->id }}" {{  ( $record->course_type_id ?? 0 ) == $course_type->id ? 'selected' : '' }}>{{ $course_type->title }}</option>
															@endforeach
														</select>
														@error('course_type_id') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Course Type -->

												<!-- Course Category -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="course_category_id">Course Category <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 set_course_categories get_course_level_options" id="course_categories" name="course_category_id" data-id="{{ $record->course_category_id ?? 0 }}" required>
															<option value="" disabled selected>Choose...</option>
														</select>

														@error('course_category_id') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Course Category -->

												<!-- Course Level -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="course_level_id">Course Level <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 set_course_level_options get_discipline_options" id="course_levels" name="course_level_id" data-id="{{ $record->course_level_id ?? 0 }}" required>
															<option value="" disabled selected>Choose...</option>
														</select>

														@error('course_level_id') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Course Level -->

												<!-- Discipline -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="discipline_id">Discipline <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 set_discipline_options get_course_options" id="discipline" name="discipline_id" data-id="{{ $record->discipline_id ?? 0 }}" data-id="{{ $record->discipline_id ?? 0 }}" required>
															<option value="" disabled selected>Choose...</option>
														</select>

														@error('discipline_id') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Discipline -->

												<!-- Course -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="course_id">Course <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 set_course_options" id="courses" name="course_id" data-id="{{ $record->course_id ?? 0 }}" data-id="{{ $record->course_id ?? 0 }}" required>
															<option value="" disabled selected>Choose...</option>
														</select>

														@error('course_id') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Course -->

											</div>
										</div>

										<div class="col-md-12">
											<hr>
											<h4 class="text-center">
												{{ $record->course->title ?? '' }} ( Course Details )
											</h4>
											<hr>
										</div>


										<!-- Course Details -->
										<div class="col-md-12">
											<details open class="my-2" style="padding: 12px; background: aliceblue;">
												<summary>Details</summary>
												<div class="row p-4">

													<!-- Eligibility -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="eligibility">Eligibility</label>
															<input type="text" class="form-control" id="eligibility" name="eligibility" placeholder="Enter eligibility" value="{{ old('eligibility', $record->eligibility ?? '') }}">
															@error('eligibility') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Eligibility -->

													<!-- Duration -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="duration">Duration</label>
															<input type="text" class="form-control" id="duration" name="duration" placeholder="Enter duration" value="{{ old('duration', $record->duration ?? '') }}">
															@error('duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Duration -->

													<!-- Total Intake -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="total_intake">Total Intake</label>
															<input type="text" class="form-control" id="total_intake" name="total_intake" placeholder="Enter total intake" value="{{ old('total_intake', $record->total_intake ?? '') }}">
															@error('total_intake') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Total Intake -->

													<!-- Medium -->
													<div class="col-md-4 medium_col">
														<div class="form-group">
															<label for="medium">Medium</label>
															<input type="text" class="form-control" id="medium" name="medium" placeholder="Enter medium" value="{{ old('medium', $record->medium ?? '') }}">
															@error('medium') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Medium -->

													<!-- Regular Mode Duration -->
													<div class="col-md-4 regular_mode_duration_col">
														<div class="form-group">
															<label for="regular_mode_duration">Regular Mode Duration</label>
															<input type="text" class="form-control" id="regular_mode_duration" name="regular_mode_duration" placeholder="Enter regular mode duration" value="{{ old('regular_mode_duration', $record->regular_mode_duration ?? '') }}">
															@error('regular_mode_duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Regular Mode Duration -->

													<!-- External Mode Duration -->
													<div class="col-md-4 external_mode_duration_col">
														<div class="form-group">
															<label for="external_mode_duration">External Mode Duration</label>
															<input type="text" class="form-control" id="external_mode_duration" name="external_mode_duration" placeholder="Enter external mode duration" value="{{ old('external_mode_duration', $record->external_mode_duration ?? '') }}">
															@error('external_mode_duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End External Mode Duration -->

													<!-- Fee Reg Ext -->
													<div class="col-md-4 fee_reg_ext_col">
														<div class="form-group">
															<label for="fee_reg_ext">Fee Reg Ext</label>
															<input type="text" class="form-control" id="fee_reg_ext" name="fee_reg_ext" placeholder="Enter fee reg ext" value="{{ old('fee_reg_ext', $record->fee_reg_ext ?? '') }}">
															@error('external_mode_duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee Reg Ext -->

													<!-- Admission Process -->
													<div class="col-md-4 admission_process_col">
														<div class="form-group">
															<label for="admission_process">Admission Process</label>
															<input type="text" class="form-control" id="admission_process" name="admission_process" placeholder="Enter admission process" value="{{ old('admission_process', $record->admission_process ?? '') }}">
															@error('external_mode_duration') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Admission Process -->

													<!-- Order -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="sequence">Order</label>
															<input type="number" class="form-control" id="sequence" name="sequence" placeholder="Enter sequence" value="{{ old('sequence', $record->sequence ?? count($records ?? []) + 1) }}" min="1" max="1000">
															@error('sequence') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Order -->

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
										</div>
										<!-- End Course Details -->

										<!-- Fee -->
										<div class="col-md-12 fee-col">
											<details open class="my-2" style="padding: 12px; background: aliceblue;">
												<summary>Fee</summary>
												<div class="row p-4">
													<!-- Fee Above 85 -->
													<div class="col-md-4 fee_above_85_col">
														<div class="form-group">
															<label for="fee_above_85">Fee Above 85</label>
															<input type="text" class="form-control" id="fee_above_85" name="fee_above_85" placeholder="Enter fee above 85" value="{{ old('fee_above_85', $record->fee_above_85 ?? '') }}">
															@error('fee_above_85') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee Above 85 -->

													<!-- Fee Above 85 -->
													<div class="col-md-4 fee_between_75_to_55_col">
														<div class="form-group">
															<label for="fee_between_75_to_55">Fee Above 85 To 75</label>
															<input type="text" class="form-control" id="fee_between_75_to_55" name="fee_between_75_to_55" placeholder="Enter fee above 85 to 75" value="{{ old('fee_between_75_to_55', $record->fee_between_75_to_55 ?? '') }}">
															@error('fee_between_75_to_55') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee Above 85 -->

													<!-- Fee Between 75 To 55 -->
													<div class="col-md-4 fee_between_75_to_55_col">
														<div class="form-group">
															<label for="fee_between_75_to_55">Fee Between 75 To 55</label>
															<input type="text" class="form-control" id="fee_between_75_to_55" name="fee_between_75_to_55" placeholder="Enter fee between 75 to 55" value="{{ old('fee_between_75_to_55', $record->fee_between_75_to_55 ?? '') }}">
															@error('fee_between_75_to_55') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee Between 75 To 55 -->

													<!-- Fee Below 55 -->
													<div class="col-md-4 fee_below_55_col">
														<div class="form-group">
															<label for="fee_below_55">Fee Below 55</label>
															<input type="text" class="form-control" id="fee_below_55" name="fee_below_55" placeholder="Enter fee below 55" value="{{ old('fee_below_55', $record->fee_below_55 ?? '') }}">
															@error('fee_below_55') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee Below 55 -->

													<!-- Fee for woman -->
													<div class="col-md-4 fee_for_woman_col">
														<div class="form-group">
															<label for="fee_for_woman">Fee for woman</label>
															<input type="text" class="form-control" id="fee_for_woman" name="fee_for_woman" placeholder="Enter fee for woman" value="{{ old('fee_for_woman', $record->fee_for_woman ?? '') }}">
															@error('fee_for_woman') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee for woman -->

													<!-- Fee for men -->
													<div class="col-md-4 fee_for_men_col">
														<div class="form-group">
															<label for="fee_for_men">Fee for men</label>
															<input type="text" class="form-control" id="fee_for_men" name="fee_for_men" placeholder="Enter fee for men" value="{{ old('fee_for_men', $record->fee_for_men ?? '') }}">
															@error('fee_for_men') <div class="text-danger">{{ $message }}</div> @enderror
														</div>
													</div>
													<!-- End Fee for men -->
												</div>
										</div>
										<!-- End Fee -->

										<div class="col-md-12">
											<details open class="my-2" style="padding: 12px; background: aliceblue;">
												<summary>Media</summary>
												<div class="row p-4">

													<!-- Syllabus File -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="syllabus_file">Syllabus File</label>
															<input type="file" class="form-control p-1" id="syllabus_file" name="syllabus_file">
															@error('syllabus_file') <div class="text-danger">{{ $message }}</div> @enderror

															@if(isset($record) && $record->syllabus_file != '')
															<input type="hidden" name="old_syllabus_file" value="{{ $record->syllabus_file }}">
															<a href="{{ $record->syllabus_file_url }}" alt="" class="mt-2" target="_blank">View</a>
															@endif
														</div>
													</div>
													<!-- Syllabus File -->

													<!-- Brochure File -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="brochure_file">Brochure File</label>
															<input type="file" class="form-control p-1" id="brochure_file" name="brochure_file">
															@error('brochure_file') <div class="text-danger">{{ $message }}</div> @enderror

															@if(isset($record) && $record->brochure_file != '')
															<input type="hidden" name="old_brochure_file" value="{{ $record->brochure_file }}">
															<a href="{{ $record->brochure_file_url }}" alt="" class="mt-2" target="_blank">View</a>
															@endif
														</div>
													</div>
													<!-- End Brochure File -->

													<!-- Text Book File -->
													<div class="col-md-4">
														<div class="form-group">
															<label for="text_book_file">Text Book File</label>
															<input type="file" class="form-control p-1" id="text_book_file" name="text_book_file">
															@error('text_book_file') <div class="text-danger">{{ $message }}</div> @enderror

															@if(isset($record) && $record->text_book_file != '')
															<input type="hidden" name="old_text_book_file" value="{{ $record->text_book_file }}">
															<a href="{{ $record->text_book_file_url }}" alt="" class="mt-2" target="_blank">View</a>
															@endif
														</div>
													</div>
													<!-- End Text Book File -->

												</div>
											</details>
										</div>

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

										<!-- Meta Tab -->
										<div class="col-md-12 ">
											<details class="my-2" {{ isset($record->id) ? 'open' : '' }} style="padding: 12px; background: aliceblue;">
												<summary>Meta</summary>
												<div class="col-md-12 mt-4">
													<div class="form-group">
														<label for="meta_title">Meta Title</label>
														<input type="text" class="form-control" id="meta_title" name="meta[meta_title]" placeholder="Enter meta title" value="{{ old('meta_title', $record->meta->meta_title ?? '') }}">
													</div>
												</div>

												<div class="col-md-12 mt-4">
													<div class="form-group">
														<label for="meta_keywords">Meta Keywords</label>
														<input type="text" class="form-control" id="meta_keywords" name="meta[meta_keywords]" placeholder="Enter meta keywords" value="{{ old('meta_keywords', $record->meta->meta_keywords ?? '') }}">
													</div>
												</div>

												<div class="col-md-12">
													<div class="form-group">
														<label for="name">Meta Description</label>
														<textarea type="text" class="form-control" id="meta_description" name="meta[meta_description]" placeholder="Enter meta description">{{ old('meta_description', $record->meta->meta_description ?? '') }}</textarea>
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
// Dynamic Data Fetched
@if($record->course_type_id ?? 0)
		$('.get_course_category_options').trigger('change')
		$('.get_course_level_options').trigger('change')
		$('.get_discipline_options').trigger('change')
		$('.get_course_options').trigger('change')
	@endif
    // End Dynamic Data Fetched

</script>
@endsection