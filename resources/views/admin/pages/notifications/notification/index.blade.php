@extends('admin.app')

@section('content')


<!-- Main content -->
<section class="content">
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-12">


				<div class="card  mt-5">

					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-sm-6 text-left">
								<h5 class="m-0">{{ str()->plural($shared_data->page_title) }} </h5>
							</div>
							@can('permissions', [$shared_data->permission_key, 'create'])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route($shared_data->create_route) }}">Add</a>
							</div>
							@endcan
						</div>
					</div>
					<div class="card-body">
						@if(session()->has('back_msg'))
							{!! session()->get('back_msg') !!}
						@endif
						<div class="row">
							<div class="col-12">
								<div class="table-responsive">
								<table id="example2" class="table table-bordered table-hover">
									<thead>
										<tr>
											<th style="width: 50px;" class="text-center">#</th>
											<th>Title</th>
											<th>Content</th>
											<th>Sent Type</th>
											<th>Schedule / Regular Time</th>
											<th>Date</th>
											<th>Status</th>
											<th style="width: 100px;" class="text-center">Action</th>
										</tr>
									</thead>
									<tbody>
										@foreach ($records ?? [] as $record)
										<tr>
											<td class="text-center">{{ $loop->iteration }}</td>
											<td>{{ $record->title }}</td>
											<td>{!! $record->content ?? '' !!}</td>
											<td>{{ ucwords($record->sent_type) }}</td>
											<td>
											    @if($record->regular_time)
													{{ date('h:i a', strtotime($record->regular_time)) }} 
												@endif
												
											    @if($record->schedule_date_time)
											    {{ 
													date('F d, Y ( h:i a )', strtotime($record->schedule_date_time))
												}}
												@endif
											</td>
												<td>
												    {{
													date('F d, Y ( h:i a )', strtotime($record->created_at)) 
													}}
												</td>
											<td>{!! $record->status_view !!}</td>
											<td class="text-center">
												@can('permissions', [$shared_data->permission_key, 'edit'])
												<a href="{{ route($shared_data->edit_route,$record->id) }}" class="btn btn-success btn-sm"><i class="fa fa-edit"></i></a>
												@endcan

												@can('permissions', [$shared_data->permission_key, 'delete'])
												<form action="{{ route($shared_data->destroy_route,$record->id) }}" method="post" class="d-inline">
													@csrf
													@method('DELETE')
													<button type="button" class="btn btn-danger btn-sm ml-2" onclick="deleteRecord(this)"><i class="fa fa-trash"></i></button>
												</form>
												@endcan
											</td>
										</tr>
										@endforeach

										</tfoot>
								</table>
							</div>
							</div>
							<!-- /.card-body -->
						</div>
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
	$(function() {
		$('#example2').DataTable({
			"paging": true,
			"lengthChange": true,
			"searching": true,
			"ordering": true,
			"info": true,
			"autoWidth": false,
			"responsive": true,
		});
	});

	function deleteRecord(th) {
		Swal.fire({
			title: 'Are you sure?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			showConfirmButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: 'Yes, delete it!'
		}).then((result) => {
			if (result.value == 1) {
				$(th).parent().submit();
			}
		});
	}
</script>
@endsection