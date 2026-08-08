@extends('admin.app')

@section('content')


<!-- Main content -->
<section class="content">
	<div class="container ">

		<div class="row">
			<div class="col-12">

				<div class="card mt-5">
					<div class="card-header bg-theme">
						<div class="row">
							<div class="col-sm-6">

								<h5 class="m-0">Role List</h5>
							</div>

							@can('permissions', ['roles', 'create'])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ url('admin/roles/create') }}">Add</a>
							</div>
							@endcan
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

						<table id="example2" class="table table-bordered table-hover">
							<thead>
								<tr>
									<th style="width: 50px;" class="text-center">ID</th>
									<th>Name</th>
									<th style="width: 70px;" class="text-center">Permission</th>
									<th style="width: 100px;" class="text-center">Action</th>
								</tr>
							</thead>
							<tbody>

								@foreach ($roles as $row)
									<tr>
										<td>{{ $row->id }}</td>
										<td>{{ $row->name }}
										</td>
										<td class="text-center">
											@if(isset($permissions['role-permissions']) && $permissions['role-permissions']['rr_edit']==1)
											<a href="{{ route('admin::role_permission',$row->id) }}" class="btn btn-info btn-sm"><i class="fa fa-lock"></i></a>
											@endif
										</td>
										<td class="text-center">
											@if(isset($permissions['roles']) && $permissions['roles']['rr_edit']==1)
											<a href="{{ route('admin::roles.edit',$row->id) }}" class="btn btn-success btn-sm"><i class="fa fa-edit"></i></a>
											@endif

											@if($row->id != 1)
												@if(isset($permissions['roles']) && $permissions['roles']['rr_delete']==1)
												<form action="{{ route('admin::roles.destroy',$row->id) }}" method="post" class="d-inline">
													@csrf
													@method('DELETE')
													<button type="button" class="btn btn-danger btn-sm ml-2" onclick="deleteRecord(this)"><i class="fa fa-trash"></i></button>
												</form>
												@endif
											@endif
										</td>
									</tr>
								@endforeach

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