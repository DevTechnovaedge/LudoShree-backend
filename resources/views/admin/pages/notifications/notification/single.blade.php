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
			<div class="col-md-12 mx-auto mt-5">


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

												<!-- Content -->
												<div class="col-md-12">
													<div class="form-group">
														<label for="content">Content <span class="text-danger">*</span></label>
														<textarea class="form-control" placeholder="Enter content" name="content" id="content" required>{{ old('content', $record->content ?? '') }}</textarea>
														@error("content") <span class="text-danger">{{ $message }}</span> @enderror
													</div>
												</div>
												<!-- End Content -->

												<!-- Sent -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="Sent">Sent</label>
														<select name="sent_type" id="sent_type" class="form-control">
															<option value="instant" @selected(($record->sent_type ?? old('sent_type') ) == 'instant')>Instant</option>
															<option value="schedule" @selected(($record->sent_type ?? old('sent_type') ) == 'schedule')>Schedule</option>
															<option value="regular" @selected(($record->sent_type ?? old('sent_type') ) == 'regular')>Regular</option>
														</select>
														@error('sent_type') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Sent -->

												<!-- Schedule -->
												<div class="col-md-4 schedule-container {{ ( $record->sent_type ?? old('sent_type') ) == 'schedule' ? '' : 'd-none' }}">
													<div class="form-group">
														<label for="schedule">Schedule <span class="text-danger">*</span></label>
														<input type="datetime-local" class="form-control" name="schedule_date_time" id="schedule_date_time" value="{{ $record->schedule_date_time ?? '' }}">
														@error('schedule_date_time') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Schedule -->

												<!-- Regular Time -->
												<div class="col-md-4 regular-container {{ ( $record->sent_type ?? old('sent_type') ) == 'regular' ? '' : 'd-none' }}">
													<div class="form-group">
														<label for="regular">Regular Time <span class="text-danger">*</span></label>
														<input type="time" class="form-control" name="regular_time" id="regular_time" value="{{ $record->regular_time ?? '' }}">
														@error('regular_time') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Regular Time -->

												<!-- Users -->
												<div class="col-md-4">
													<div class="form-group">
														<label for="status">Users <span class="text-danger">*</span></label>
														<select type="text" class="form-control select2 multi-select2" id="users" name="user_ids[]" multiple>
														    
															 <option value="0" class="default-option" {{ in_array(0, $record->user_ids ?? []) ? 'selected' : '' }}>All</option>
															
															@foreach(App\Models\User::get() as $user)
															<option value="{{ $user->id }}" {{ in_array($user->id, $record->user_ids ?? []) ? 'selected' : '' }}>{{ $user->name }} - {{ $user->mobile }}</option>
															@endforeach
														</select>
														@error('user_ids') <div class="text-danger">{{ $message }}</div> @enderror
													</div>
												</div>
												<!-- End Users -->

											</div>
										</div>

										<div class="col-md-12 mt-4">
											<div class="text-center">
												<button type="submit" class="btn bg-theme btn-lg" onclick="this.disabled=true; this.form.submit();">Submit</button>
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
<script>

 /** */
  $(document).ready(function() {
    // Initialize Select2
    $('.multi-select2').select2({
      placeholder: 'Choose...',
      allowClear: true
    });
  });

  $('.multi-select2').on('select2:select', function(e) {
    var data = e.params.data;
    if(data.id == 0){
      $('.multi-select2 option').prop('selected', false)
      $('.default-option').prop('selected', true)
      $('.multi-select2').trigger('change')
    }else{
      $('.default-option').prop('selected', false)
      $('.multi-select2').trigger('change')
    }
  });
  /** */
  
	$('#sent_type').on('change', function() {
		var schedule_container = $('.schedule-container');
		var schedule_date_time = $('.schedule_date_time');
		
		var  regular_container 	= $('.regular-container');
		var  regular_time 		= $('.regular_time');

        schedule_container.addClass('d-none');
			schedule_date_time.prop('required', false)

			regular_container.addClass('d-none');
			regular_time.prop('required', false)
			
		if(this.value == 'schedule'){
			schedule_container.removeClass('d-none')
			schedule_date_time.prop('required', true)
		}
		else if(this.value == 'regular'){
			regular_container.removeClass('d-none')
			regular_time.prop('required', true)
		}
		
	});
</script>
@endsection