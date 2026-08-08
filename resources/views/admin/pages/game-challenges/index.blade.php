@extends('admin.app')

@section('style')
<style>
	.select2-container--default .select2-selection--single .select2-selection__arrow {
		top: 5px;
	}

	td {
		white-space: nowrap;
	}

	#game-challenge-datatable_filter{ display: none; }
	
	@if(request()->filter == 'uncomplete_games' || request()->filter == 'uncomplete_cancel_games' || request()->filter == 'dispute_games')
		.ludo-king-result-view-btn{ display:block; }
	@else
	    .ludo-king-result-view-btn{ display:none; }
	@endif
	
	@if(request()->filter != 'pending_challenges')
	    .game-challenge-delete-btn{ display:none; }
	@endif

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
									<table id="game-challenge-datatable" class="table table-bordered table-hover">
										<thead>
											<tr>
												<th style="width: 50px;" class="text-center">#</th>
												<th>Game</th>
												<th>Challenger</th>
												<th>Roomcode</th>
												<th>Opponent</th>
												<th>Amount</th>
												<th>Admin</th>
												<th>Paid</th>
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

		table = $('#game-challenge-datatable').DataTable({
			dom: 'lBfrtip',
			columnDefs: [{
				"defaultContent": "-",
				"targets": "_all"
			}],
			processing: true,
			serverSide: true,
			ajax: {
				method: 'GET',
				url: "{{ url('admin/game-challenges') }}",
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
					data: 'game_details',
					name: 'uid'
				},
				{
					data: 'challenger_details',
					name: 'challenger.uid'
					
				},
				{
					data: 'roomcode_details',
					name: 'roomcode'
				},
				{
					data: 'opponent_details',
					name: 'opponent.uid'
				},
				{
					data: 'amount',
					name: 'amount',
					
				},
				{
					data: 'game_commission_amount',
					name: 'challenger.name'
				},
				{
					data: 'paid_amount',
					name: 'opponent.name'
				}
			]
		});

	});
</script>

@endsection