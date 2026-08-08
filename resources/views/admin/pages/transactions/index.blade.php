@extends('admin.app')

@section('style')
<style>
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		top: 5px;
	}

	td {
		white-space: nowrap;
	}
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
									<table id="transactions-datatable" class="table table-bordered table-hover">
										<thead>
											<tr>
												<th style="width: 50px;" class="text-center">#</th>
												<th>User</th>
												<th>TxnID</th>
												<th>Payment Info</th>
												<th>Amount</th>
												<th>Type</th>
												<th>Date</th>
												@if(request()->filter == 'pending-withdrawals')
												<th>Action</th>
												@else
												<th>Status</th>
												@endif
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

	$(function() {

		table = $('#transactions-datatable').DataTable({
			dom: 'lrtip',
			columnDefs: [{
				"defaultContent": "-",
				"targets": "_all"
			}],
			processing: true,
			serverSide: true,
			ajax: {
				method: 'GET',
				url: "{{ url('admin/transactions') }}",
				data: function(d) {
					// d.course_type_id = $('#course_types').val();
					d.filter = "{{ request()->filter }}";
					d.date = "{{ request()->date }}";
				}
			},
			columns: [{
					data: 'DT_RowIndex',
					orderable: false,
					searchable: false
				},
				{
					data: 'user_details',
					name: 'user_details'
				},
				{
					data: 'txn_id',
					name: 'txn_id'
				},
				{
					data: 'payment_info',
					name: 'payment_info'
				},
				{
					data: 'amount',
					name: 'amount'
				},
				{
					data: 'transfer_type',
					name: 'transfer_type'
				},
				{
					data: 'created_at',
					name: 'created_at'
				},
				{
					data: 'status_view',
					name: 'status_view'
				}
			]
		});

	});

	/**
	 * approve-withdrawal-btn
	 */
	$(document).on('click', '.transaction-action-btn', function() {
		var id = $(this).data('id')
		var type = $(this).data('type')
		var action = $(this).data('action')

		var buttonText = "";
		var resSwalTitle = "";
		var resSwalText = "";
		var resSwalIcon = "";
		var isRemarkEnable = false;

		switch (type) {
			case 'withdrawal':
				if (action == 'approve') {
					buttonText = "Yes, approve it!";
					resSwalTitle = "Approved!"
					resSwalText = "Transaction successfully apparoved"
					resSwalIcon = "success"
					isRemarkEnable = false
				}

				if (action == 'reject') {
					buttonText = "Yes, reject it!";
					resSwalTitle = "Rejected!"
					resSwalText = "Transaction successfully rejected"
					resSwalIcon = "success"
					isRemarkEnable = true
				}
				break;

			case 'deposit':
				if (action == 'approve') {
					buttonText = "Yes, approved it!";
					resSwalTitle = "Approved!"
					resSwalText = "Transaction successfully apparoved"
					resSwalIcon = "success"
					isRemarkEnable = false
				}

				if (action == 'reject') {
					buttonText = "Yes, reject it!";
					resSwalTitle = "Rejected!"
					resSwalText = "Transaction successfully rejected"
					resSwalIcon = "success"
					isRemarkEnable = true
				}
				break;
		}

		update_transaction({
			id: id,
			type: type,
			actionType: action,
			buttonText: buttonText,
			resSwalTitle: resSwalTitle,
			resSwalText: resSwalText,
			resSwalIcon: resSwalIcon,
			isRemarkEnable: isRemarkEnable
		})
	})


	function update_transaction({
		id,
		type,
		actionType,
		buttonText,
		resSwalTitle,
		resSwalText,
		resSwalIcon,
		isRemarkEnable
	}) {

		Swal.fire({
			title: 'Are you sure?',
			text: "You won't be able to revert this!",
			icon: 'warning',
			showCancelButton: true,
			showConfirmButton: true,
			confirmButtonColor: '#3085d6',
			cancelButtonColor: '#d33',
			confirmButtonText: buttonText,
			input: isRemarkEnable ? 'textarea' : '', // This adds a textarea input for remarks
			inputPlaceholder: 'Enter your remarks here...', // Placeholder text for the textarea
			inputAttributes: {
				'aria-label': 'Type your remarks here'
			},
			preConfirm: (remark) => {
				// if (!remark) {
				// 	Swal.showValidationMessage('Please enter your remarks');
				// } else {
				// return remark; // You can return the user's remark for further use
				// }

				Swal.showLoading(); // Show loading on the confirm button
				return remark;
			}
		}).then((result) => {
			var remark = "";
			if (result.isConfirmed) {
				remark = result.value; // User's remark will be available here
				// }

				// if (result.value == 1) {

				$.ajax({
					method: 'POST',
					url: "{{ url('admin/update-transaction-status') }}",
					data: {
						"_token": "{{ csrf_token() }}",
						id: id,
						type: type,
						actionType: actionType,
						remark: remark,
					},
					dataType: 'json',
					beforeSend: (res) => {
						please_wait()
					},
					success: (res) => {
						if (res.status) {
							table.ajax.reload();
							Swal.fire({
								title: resSwalTitle,
								text: resSwalText,
								icon: resSwalIcon
							});
						}
					},
					error: (res) => {
						Swal.fire({
							title: 'Error',
							text: 'Something went wrong. Please try again.',
							icon: 'error'
						});
					}
				})

			}
		});
	}
</script>
@endsection