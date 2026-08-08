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
			<div class="col-md-5 mx-auto mt-5">


				<div class="card mt-4">
					<div class="card-header bg-theme">
						<div class="row">
							<div class="col-sm-6 text-left align-self-center">
								<h5 class="m-0">{{ $shared_data->page_title }}</h5>
							</div>
							@can('permissions', [ $shared_data->permission_key, 'create' ] ?: [])
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
								<form method="post" action="{{ route($shared_data->store_route) }}" enctype="multipart/form-data">
									@csrf
									<input type="hidden" name="id" value="{{ $record->id ?? '' }}">
									<div class="row">
										<div class="col-md-12">
											<div class="row p-3">


												<!-- Title -->
												<div class="col-md-12">
													<div class="form-group">
														<label for="title">Title <span class="text-danger">*</span></label>
														<input type="text" class="form-control" id="title" name="title" placeholder="Enter title" value="{{ old('title', $record->title ?? '') }}" required>
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

											</div>
										</div>

										<div class="col-md-12 mt-4">
											<div class="text-center">
												<button type="submit" class="btn bg-theme btn-lg">Submit</button>
											</div>
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

@endsection