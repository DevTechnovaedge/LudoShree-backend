@extends('admin.app')

@section('style')
<style>
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		top: 5px;
	}

	td { white-space: nowrap; }
</style>
@endsection

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
							<!-- @can('permissions', [$shared_data->permission_key, 'create'])
							<div class="col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route($shared_data->create_route) }}">Add</a>
							</div>
							@endcan -->
						</div>
					</div>
					<div class="card-body p-4">
						@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif

						<div class="row">
							
							<div class="col-12">
								<div class="table-responsive">
								<table id="wallet-transactions-datatable" class="table table-bordered table-hover">
									<thead>
										<tr>
											<th style="width: 50px;" class="text-center">#</th>
											<th class="{{ ( request()->segment(2) == 'game-commissions' ) ? 'd-none' : '' }}" >Name</th>
											<th>Perticular</th>
											<th>Commission ( % )</th>
											<th>Amount ( ₹ )</th>
											<th>Date</th>
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
	var table;

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

		 table = $('#wallet-transactions-datatable').DataTable({
			processing: true,
			serverSide: true,
			// order : [[6, 'asc']],
			ajax: {
				method: 'GET',
				url: "{{ url('admin/'.request()->segment(2)) }}",
				data: function(d) {
					// d.course_type_id = $('#course_types').val();
					d.filter = "{{ request()->filter }}";
							}
			},
			columns: [{
					data: 'DT_RowIndex',
					orderable: false,
					searchable: false
				},
				{
					data: 'user_details',
					name: 'user_details',
					class:"{{ ( request()->segment(2) == 'game-commissions' ) ? 'd-none' : '' }}"
				},
				{
					data: 'remark',
					name: 'remark'
				},
				{
					data: 'commissions',
					name: 'commissions'
				},
				{
					data: 'amount',
					name: 'amount'
				},
				{
					data: 'created_at',
					name: 'created_at'
				}
			]
		});

</script>
@endsection