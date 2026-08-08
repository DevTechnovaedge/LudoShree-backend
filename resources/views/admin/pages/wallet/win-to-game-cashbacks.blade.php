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
								<h5 class="m-0">Win to Game Cashbacks</h5>
							</div>
						</div>
					</div>
					<div class="card-body p-4">
						@if(session()->has('back_msg'))
							{!! session()->get('back_msg') !!}
						@endif

						<div class="row">
							
							<div class="col-12">
								<div class="table-responsive">
								<table id="win-to-game-cashbacks" class="table table-bordered table-hover">
									<thead>
										<tr>
											<th style="width: 50px;" class="text-center">#</th>
											<th>Name</th>
											<th>Cashback Percentage ( % )</th>
											<th>Cashback Amount</th>
											<th>Actual Win Amount</th>
											<th>Transferred Win Amount</th>
											<th>Remaining Win Amount</th>
											<th>Actual Game Amount</th>
											<th>Game Amount Without Cashback</th>
											<th>Game Amount With Cashback</th>
											<th>Created at</th>
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
		 table = $('#win-to-game-cashbacks').DataTable({
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
				url: "{{ url('admin/win-to-game-cashbacks') }}",
				data: function(d) {
					d.date = "{{ request()->date }}";
					}
			},
			columns: [{
					data: 'DT_RowIndex',
					orderable: false,
					searchable: true,
					name:"user.uid"
				},
				{
					data: 'user.user_details',
					name:"user.mobile"
				},
				{
					data: 'cashback_percentage',
					name: "cashback_percentage",
					searchable: true,
				},
				{
					data: 'cashback_amount',
					name: 'cashback_amount'
				},
				{
					data: 'actual_win_amount',
					name: 'actual_win_amount'
				},
				{
					data: 'transferred_win_amount',
					name: 'transferred_win_amount',
				},
				{
					data: 'remaining_win_amount',
					name: 'remaining_win_amount',
				},
				{
					data: 'actual_game_amount',
					name: 'actual_game_amount',
				},
				{
					data: 'game_amount_without_cashback',
					name: 'game_amount_without_cashback',
				},
				{
					data: 'game_amount_with_cashback',
					name: 'game_amount_with_cashback',
				},
				{
					data: 'created_at',
					name: 'created_at'
				}
			]
		});

	});

	</script>
@endsection