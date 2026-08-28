@extends('admin.app')

@section('content')

<style>
	div#users-datatable_length {
		margin-right: 1rem;
	}

	#users-datatable_filter{ display: none; }
</style>
<!-- Main content -->
<section class="content">
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-12">

				<div class="card mt-5">

					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-6 col-sm-6 text-left">
								<h5 class="m-0">{{ str()->plural($shared_data->page_title) }} </h5>
							</div>
							@can('permissions', [$shared_data->permission_key, 'create'])
							<div class="col-6 col-sm-6 text-right">
								<a class="btn btn-warning" href="{{ route($shared_data->create_route) }}" target='_balnk'>Add</a>
							</div>
							@endcan
						</div>
					</div>
					<div class="card-body px-4">
						@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif
						<div class="row">
							<div class="col-12">
								<div class="filter-section my-4">
									<details open class="p-3">
										<summary>Filter</summary>
										<div class="row">
											@if(request()->date)
											<div class="col-md-12 mb-2">
												<small class="text-muted">Showing users registered on <strong>{{ request()->date }}</strong></small>
											</div>
											@endif
											<div class="col-md-3">
												<label for="">From</label>
												<input type="date" class="form-control" name="from_date" id="from_date" value="{{ request()->date }}">
											</div>
											<div class="col-md-3">
												<label for="">To</label>
												<input type="date" class="form-control" name="to_date" id="to_date" value="{{ request()->date }}">
											</div>
										</div>
								</div>
								</details>
							</div>

							<div class="col-12">
								<div class="table-responsive">
									<table id="users-datatable" class="table table-bordered table-hover">
										<thead>
											<tr>
												<th style="width: 50px;" class="text-center">#</th>
												<th>Name</th>
												<th>Email</th>
												<th>Mobile</th>
												<th>Otp</th>
												<th>Game Play</th>
												<th>Referral</th>
												<th>Withdrawal</th>
												<th>Total Balance</th>
												<th>Refer By</th>
												<th>Date</th>
												<th>Status</th>
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
	$(function() {

		table = $('#users-datatable').DataTable({
			dom: 'lBfrtip',
			pageLength: 10, // Set this to control the number of records per page
			lengthMenu: [
				[10, 25, 50, 100, -1],
				[10, 25, 50, 100, "All"]
			], // Customize page lengths

			buttons: [
				// {
				//     extend: 'copyHtml5',
				//     exportOptions: {
				//         columns: ':visible'
				//     },
				// },
				{
					extend: 'excelHtml5',
					exportOptions: {
						columns: ':visible'
					},
				},
				{
					extend: 'csvHtml5',
					exportOptions: {
						columns: ':visible'
					},
				},
				{
					extend: 'pdfHtml5',
					exportOptions: {
						columns: ':visible'
					},
				},
				{
					extend: 'print',
					autoPrint: false,
					text: "View",
					exportOptions: {
						columns: ':visible'
					},
				}
			],
			columnDefs: [{
				"defaultContent": "-",
				"targets": "_all"
			}],
			// searching: false,
			// orderable:false,
			processing: true,
			serverSide: true,
			ajax: {
				method: 'GET',
				url: "{{ url('admin/users') }}",
				data: function(d) {
					d.filter = "{{ request()->filter }}";
					d.date = "{{ request()->date }}";
					d.from = $('#from_date').val();
					d.to = $('#to_date').val();
					// d.course_type_id = $('#course_types').val();
				}
			},
			columns: [{
					data: 'DT_RowIndex',
					name:"uid"
				},
				{
					data: 'name'
				},
				{
					data: 'email'
				},
				{
					data: 'mobile'
				},
				{
					data: 'otp_view',
					searchable:false,
					orderable:false
				},
				{
					data: 'game_play_count',
					searchable: false,
					orderable: false
				},
				{
					data: 'refer_count',
					searchable: false,
					orderable: false
				},
				{
					data: 'withdrawal_status_view',
					searchable:false,
					orderable:false
				},
				{
					data: 'total_wallet_amount',
					searchable:false,
					orderable:false
				},
				{
					data: 'refer_by',
					name:'refer_by_user.uid',
					orderable:false
					
				},
				{
					data: 'created_at'
				},

				{
					data: 'status_view',
					searchable:false,
					orderable:false
				}
			]
		});

	});

	$(document).on('change', '#from_date, #to_date', function() {
		table.ajax.reload()
	})

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