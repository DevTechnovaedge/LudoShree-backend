@extends('admin.app')

@section('style')
<style>
	.select2-container--default .select2-selection--single .select2-selection__arrow {
	    top: 5px;
	}

	/* td { white-space: nowrap; } */
</style>
@endsection

@section('content')

<!-- Main content -->
<section class="content">
	<div class="container">
		<div class="row">
			<div class="col-md-6 mx-auto">
				<div class="card  mt-5">
					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-sm-6 text-left">
								<h5 class="m-0">Add / Edit {{ str()->plural($shared_data->page_title) }} </h5>
							</div>

							@can('permissions', [ $shared_data->permission_key, 'view' ] ?: [])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route($shared_data->index_route) }}">List</a>
							</div>
							@endcan
						</div>
					</div>
					<div class="card-body p-4">
						<form action="{{ Route::has($shared_data->store_route) ? route($shared_data->store_route) : '#' }}" method="post" enctype="multipart/form-data">
							@csrf
							<input type="hidden" name="id" value="{{ $record->id ?? 0 }}">
							<div class="row">

								<!-- Title -->
								<div class="col-md-12">
									<div class="form-group">
										<label for="">Title <span class="text-danger">*</span></label>
										<input type="text" name="title" placeholder="Enter title" class="form-control" value="{{ $record->title ?? '' }}" required>
										@error('title') <div class="text-danger">{{ $message }}</div> @enderror
									</div>
								</div>
								<!-- End Title -->

									<!-- Status -->
								<div class="col-md-12">
									<div class="form-group">
										<label for="status">Status</label>
										<select type="text" class="form-control" id="status" name="status">
											<option value="1" {{ ( isset($record) && $record->status == 1 ) ? 'selected' : '' }}>Active</option>
											<option value="0" {{ ( isset($record) && $record->status == 0 ) ? 'selected' : '' }}>Deactive</option>
										</select>
									</div>
								</div>
								<!-- End Status -->
								
								<!-- Submit Button -->
								<div class="col-md-12 mt-4">
									<div class="text-center">
										<button type="submit" class="btn bg-theme btn">Submit</button>
									</div>
								</div>
								<!-- End Submit Button -->
							</div>
						</form>
					</div>
				</div>
			</div>
		</div>

	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
<script>
	
// Dynamic Data Fetched
	@if($record->course_type_id ?? 0)
		$('.get_course_category_options').trigger('change')
		$('.get_course_level_options').trigger('change')
		$('.get_discipline_options').trigger('change')
	@endif
    // End Dynamic Data Fetched
</script>
@endsection