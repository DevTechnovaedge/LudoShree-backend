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
			<!--  -->
			<div class="col-md-12">
				<!--  -->
				<!-- User Commission Management -->
				<div class="card mt-5 {{ request()->is('admin/game-ledger') ? 'd-none' : '' }} ">
					<div class="card-header bg-theme">
						<div class="row align-items-center">
							<div class="col-sm-6 text-left">
								<h5 class="m-0"> Wallet Transaction </h5>
							</div>
						</div>
					</div>
					<div class="card-body p-4">
						@php 
							$wallet_type 		=	'';
							switch(request()->segment(2)):
								case 'game-credit-and-debit':
									$wallet_type 		=	'game';
									break;

									case 'win-credit-and-debit':
										$wallet_type 		=	'win';
									break;
							endswitch;
						@endphp
						<form id="wallet-transaction-form">
							<input type="hidden" class="form-control mb-2" id="user_id" name="user_id">
							<input type="hidden" class="form-control mb-2" id="wallet_type" name="wallet_type" value="{{ $wallet_type }}">
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

								<!-- Amount -->
								<div class="col">
									<div class="form-group">
										<label for="amount">Amount</label>
										<input type="text" class="form-control" placeholder="Enter amount" id="amount" name="amount" required>
									</div>
								</div>
								<!-- End Amount -->

								<!-- Remark -->
								<div class="col">
									<div class="form-group">
										<label for="remark">Perticular</label>
										<input type="text" class="form-control" placeholder="Enter perticular" id="remark" name="remark">
									</div>
								</div>
								<!-- End Remark -->

								<!-- Action -->
								<div class="col">
									<div class="form-group">
										<label for="action">Action</label>

										<select name="type" id="" class="form-control" required>
											<option value="" disabled selected>Choose...</option>
											<option value="credit">Credit</option>
											<option value="debit">Debit</option>
										</select>
									</div>
								</div>
								<!-- End Action -->

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
			<!--  -->
			</div>
			<!--  -->

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
											<th>Name</th>
											<th>Purpose</th>
											<th>Win Wallet</th>
											<th>Game Wallet</th>
											<th>Amount</th>
											<th>Total</th>
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

	$(function() {

		
		 table = $('#wallet-transactions-datatable').DataTable({
			dom:'lrtip',
			columnDefs: [{
				"defaultContent": "-",
				"targets": "_all"
			}],
			// searching: false,
			processing: true,
			serverSide: true,
			// order : [0, 'desc'],
			ajax: {
				method: 'GET',
				url: "{{ url('admin/'.request()->segment(2)) }}",
				data: function(d) {
					// d.course_type_id = $('#course_types').val();
					d.filter = "{{ request()->filter }}";
					d.date = "{{ request()->date }}";
							}
			},
			columns: [{
					data: 'DT_RowIndex',
					orderable: false,
					searchable: true,
					name:"id"
				},
				{
					data: 'user_details',
					name:"user.mobile"
				},
				{
					data: 'remark',
					name: "remark",
					searchable: true,
				},
				{
					data: 'win_wallet',
					name: 'win_wallet'
				},
				{
					data: 'game_wallet',
					name: 'game_wallet'
				},
				{
					data: 'total_amount',
					name: 'total_amount',
					name:"user.uid"
				},
				{
					data: 'win_and_game_total_amount',
					name: 'win_and_game_total_amount',
					name:"user.name"
				},
				{
					data: 'created_at',
					name: 'created_at'
				}
			]
		});

	});

	/** */
	$('.fetch-user-info-btn').on('click', function() {
			let user_uid = $('#user_uid').val();

			if (!user_uid) {
				swal.fire('Error', "Please enter user id", "error")
			}

			wallet_type   = $('#wallet_type').val()
			wallet_fund =	0;

			if (user_uid) {
				$.ajax({
					type: 'GET',
					url: "{{ url('admin/fetch-user-details') }}",
					data: {
						user_uid: user_uid,
						wallet_type: wallet_type
					},
					dataType: 'json',
					success: (res) => {
						if (res.status) {
							$('#user_id').val(res.user.id)
							$('#name').val(res.user.name)
							
							if(wallet_type == 'game'){
								wallet_fund =	res.user.game_wallet_amount;
							}
							
							if(wallet_type == 'win'){
								wallet_fund =	res.user.win_wallet_amount;
							}

							$('#wallet_fund').val(wallet_fund)
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


	$("#wallet-transaction-form").validate({
			rules: {
				uid: {
					required: true,
					minlength: 2
				},
				amount: {
					required: true
				}
			},
			messages: {
				uid: {
					required: "Please enter your UID",
					minlength: "Your UID must be at least 2 characters long"
				}
			},
			submitHandler: function(form) {
				// Custom action instead of form.submit()
				event.preventDefault(); // Prevent the default form submission

				// If you want to handle the form via AJAX, you can do something like:

				$.ajax({
					url: "{{ url('admin/wallet-transaction-process') }}",
					type: 'POST',
					dataType: 'json',
					data: $(form).serialize(),
					beforeSend: (res) => {
							$('#wallet-transaction-form button').prop('disabled', true)
						},
					success: (res) => {
						$('#wallet-transaction-form button').prop('disabled', false)

						if (res.status) {
							table.ajax.reload();
							swal.fire('Success', res.message, 'success')
							$('#wallet-transaction-form')[0].reset()
						}else{
							swal.fire('Error', res.message, 'error')
						}
					},
					error: (res) => {
						$('#wallet-transaction-form button').prop('disabled', false)
						swal.fire('Error', 'Some error occured', 'error')
					}
				});

			}
		});
		/** */
</script>
@endsection