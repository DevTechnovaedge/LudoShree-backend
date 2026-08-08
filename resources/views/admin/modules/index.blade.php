@extends('admin.app')

@section('content')

<!-- Main content -->
<section class="content">
	<div class="container pt-4">

		<div class="row">
			<div class="col-12">

				<div class="card">
					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-md-6">
								<h5 class="m-0">Modules</h5>
							</div>
							<div class="col-md-6 text-right">
								<a href="{{ route('admin::modules.create') }}" class="btn btn-warning btn-sm">Add</a>
							</div>
						</div>
					</div>

					<div class="card-body">

						@if($message = Session::get('success'))
						<div class="alert alert-success">
							{{ $message }}
						</div>
						@elseif($message = Session::get('error'))
						<div class="alert alert-danger">
							{{ $message }}
						</div>
						@endif

						<table id="molecules-datatable" class="table table-bordered table-hover">
							<thead>
								<tr>
									<th style="width: 50px;" class="text-center">ID</th>
									<th>Module Name</th>
									<th>Module Code</th>
									<th style="width: 100px;" class="text-center">Action</th>
								</tr>
							</thead>
							<tbody>

								<?php foreach ($modules as $row) { ?>
									<tr>
										<td>{{ $row->id }}</td>
										<td>{{ $row->module_name }}
										<td>{{ $row->module_code }}
										</td>
										<td class="text-center">
											@can('permissions', ['modules', 'edit'])
											<a href="{{ route('admin::modules.edit',$row->id) }}" class="btn btn-success btn-sm"><i class="fa fa-edit"></i></a>
											@endcan

											@can('permissions', ['modules', 'delete'])
											<form action="{{ route('admin::modules.destroy',$row->id) }}" method="post" class="d-inline">
												@csrf
												@method('DELETE')
												<button type="button" class="btn btn-danger btn-sm ml-2" onclick="deleteRecord(this)"><i class="fa fa-trash"></i></button>
											</form>
											@endcan
										</td>
									</tr>
								<?php } ?>

								</tfoot>
						</table>
					</div>
					<!-- /.card-body -->
				</div>
				<!-- /.card -->
			</div>
		</div>

	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
<script>
	$(function() {
		$('#molecules-datatable').DataTable({
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