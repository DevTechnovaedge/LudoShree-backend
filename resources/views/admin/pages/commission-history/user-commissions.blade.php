@extends('admin.app')

@section('style')
<style>
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		top: 5px;
	}

	td {
		white-space: nowrap;
	}

	.fetch-user-info-btn {
		font-size: 12px !important;
		cursor: pointer;
	}
</style>
@endsection

@section('content')

<!-- Main content -->
<section class="content">
	<div class="container-fluid">
		<div class="row">
			<div class="col-md-12">

				<!-- User Commission Management -->

				<div class="card  mt-5">

					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-sm-6 text-left">
								<h5 class="m-0"> User Commission Management </h5>
							</div>
						</div>
					</div>
					<div class="card-body p-4">
						<form id="user-commission-form">
							@csrf
							<div class="row">
								<!-- User Id -->
								<div class="col">
									<div class="form-group">
										<label for="users">UserId / Mobile</label>
										<input type="text" class="form-control mb-2" id="user_uid" placeholder="Enter userId or mobile" name="uid">
										<div><small class="text-primary mt-2 fetch-user-info-btn">Fetch user info</small></div>
									</div>
								</div>
								<!-- User Id -->

								<!-- Name -->
								<div class="col">
									<div class="form-group">
										<label for="name">Name</label>
										<input type="text" class="form-control" placeholder="Name" id="name" readonly>
									</div>
								</div>
								<!-- Name -->

								<!-- Wallet Fund -->
								<div class="col">
									<div class="form-group">
										<label for="wallet_fund">Wallet Fund</label>
										<input type="text" class="form-control" placeholder="Wallet Fund" id="wallet_fund" readonly>
									</div>
								</div>
								<!-- End Wallet Fund -->

								<!-- Commission -->
								<div class="col">
									<div class="form-group">
										<label for="commission">Commission ( % )</label>
										<input type="text" class="form-control" placeholder="Enter Commission" id="commission" name="commission">
									</div>
								</div>
								<!-- End Commission -->

								<!-- Process Transaction -->
								<div class="col align-self-center">
									<div class="form-group">
										<div class="text-center">
											<button type="submit" class="btn btn-sm btn-info w-100">Process Transaction</button>
										</div>
									</div>
								</div>
								<!-- End Process Transaction -->
							</div>
						</form>
					</div>
				</div>
				<!-- End User Commission Management -->

				<div class="card  mt-5">

					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-sm-6 text-left">
								<h5 class="m-0"> User Commission History </h5>
							</div>
						</div>
					</div>
					<div class="card-body p-4">
						<div class="row">

							<div class="col-12">
								<div class="table-responsive">
									<table id="user-commissions-datatable" class="table table-bordered table-hover">
										<thead>
											<tr>
												<th style="width: 50px;" class="text-center">#</th>
												<th>Name</th>
												<th>Commission ( % )</th>
												<th>Date</th>
											</tr>
										</thead>
										<!-- <tbody>
											@foreach($user_commissions ?? [] as $user_commission)
											<tr>
												<td>{{ $loop->iteration }}</td>
												<td>{!! $user_commission->user_details !!}</td>
												<td>{{ $user_commission->commission }} %</td>
												<td>{{ $user_commission->updated_at }}</td>
											</tr>
											@endforeach
										</tbody> -->
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
	/** User Commission Management */

	$(document).ready(function() {

		/** End User Commission Management */

		$('.fetch-user-info-btn').on('click', function() {
			let user_uid = $('#user_uid').val();

			if (!user_uid) {
				swal.fire('Error', "Please enter user id", "error")
			}

			if (user_uid) {
				$.ajax({
					type: 'GET',
					url: "{{ url('admin/fetch-user-details') }}",
					data: {
						user_uid: user_uid
					},
					dataType: 'json',
					success: (res) => {
						if (res.status) {
							$('#name').val(res.user.name)
							$('#wallet_fund').val(res.user.game_wallet_amount)
							$('#commission').val(res.user.commission)
							// swal.fire('Success', res.message, "success")
						} else {
							swal.fire('Error', res.message, "error")
						}
					},
					error: (res) => {
						swal.fire('Error', 'Some error occured', "error")
					}
				})
			}
		})

		/** */


		table = $('#user-commissions-datatable').DataTable({
			processing: true,
			serverSide: true,
			ajax: {
				method: 'GET',
				url: "{{ url('admin/user-commissions-list') }}",
				data: function(d) {
					// d.course_type_id = $('#course_types').val();
					// d.filter = "{{ request()->filter }}";
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
					data: 'commission',
					name: 'commission'
				},
				{
					data: 'updated_at',
					name: 'updated_at'
				}
			]
		});

		/** */
		$("#user-commission-form").validate({
			rules: {
				uid: {
					required: true,
					minlength: 2
				},
				commission: {
					required: true
				}
			},
			messages: {
				uid: {
					required: "Please enter your UID",
					minlength: "Your UID must be at least 2 characters long"
				},
				commission: {
					required: "Please enter the commission amount"
				}
			},
			submitHandler: function(form) {
				// Custom action instead of form.submit()
				event.preventDefault(); // Prevent the default form submission

				// If you want to handle the form via AJAX, you can do something like:

				$.ajax({
					url: "{{ url('admin/update-user-commission') }}",
					type: 'POST',
					dataType: 'json',
					data: $(form).serialize(),
					success: (res) => {
						if (res.status) {
							table.ajax.reload();
							swal.fire('Success', res.message, 'success')
						}
					},
					error: (res) => {
						swal.fire('Error', 'Some error occured', 'error')
					}
				});

			}
		});
		/** */

	});
	/** */
</script>
@endsection