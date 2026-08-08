@extends('admin.app')

@section('content')

<!-- Main content -->
<section class="content">
	<div class="container">
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
								<a class="btn btn-warning" href="{{ Route::has($shared_data->create_route) ? route($shared_data->create_route) : '#' }}">Add</a>
							</div>
							@endcan
						</div>
					</div>
					<div class="card-body p-4">
						@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif
						<div class="row">
							<div class="col-12">
								<div class="table-responsive">
									<table id="ajax-datatable" class="table table-bordered table-hover">
										<thead>
											<tr>
												<th style="width: 50px;" class="text-center">#</th>
												<th>Title</th>
												<th>Slug</th>
												<th>Status</th>
												<th style="width: 100px;" class="text-center">Action</th>
											</tr>
										</thead>
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

	$(function() {

		var table = $('#ajax-datatable').DataTable({
			processing: true,
			serverSide: true,
			ajax: {
				method: 'GET',
				url: "{{ url('admin/pages') }}",
				data: function(d) {}
			},
			columns: [{
					data: 'DT_RowIndex',
					orderable: false,
					searchable: false
				},
				{
					data: 'title',
					name: 'title'
				},
				{
					data: 'slug',
					name: 'slug'
				},
				{
					data: 'status_view',
					name: 'status_view'
				},
				{
					data: 'action',
					name: 'action'
				},
			]
		});

	});
</script>
@endsection