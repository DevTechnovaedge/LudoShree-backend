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

	.king-challenge-badge{
		display: inline-flex;
		align-items: center;
		gap: 7px;
		padding: 5px 10px 5px 7px;
		border-radius: 999px;
		color: #fff;
		vertical-align: middle;
		box-shadow: 0 6px 14px rgba(15, 23, 42, .18);
		border: 1px solid rgba(255,255,255,.28);
		line-height: 1.1;
		max-width: 100%;
	}
	.king-challenge-badge.is-sync{
		background: linear-gradient(135deg, #0ea5e9 0%, #2563eb 55%, #1d4ed8 100%);
	}
	.king-challenge-badge.is-remote{
		background: linear-gradient(135deg, #f59e0b 0%, #ea580c 48%, #c2410c 100%);
	}
	.king-badge-icon{
		display: inline-flex;
		align-items: center;
		justify-content: center;
		width: 22px;
		height: 22px;
		border-radius: 50%;
		background: rgba(255,255,255,.18);
		box-shadow: inset 0 0 0 1px rgba(255,255,255,.25);
		flex: 0 0 auto;
	}
	.king-badge-crown{
		width: 13px;
		height: 13px;
		color: #fff7cc;
		filter: drop-shadow(0 1px 1px rgba(0,0,0,.25));
	}
	.king-badge-copy{
		display: inline-flex;
		flex-direction: column;
		gap: 1px;
		min-width: 0;
	}
	.king-badge-title{
		font-size: 11px;
		font-weight: 800;
		letter-spacing: .35px;
		text-transform: uppercase;
		white-space: nowrap;
	}
	.king-badge-sub{
		font-size: 9px;
		font-weight: 600;
		opacity: .88;
		letter-spacing: .2px;
		text-transform: uppercase;
	}
	.king-player-badge{
		display: inline-flex;
		align-items: center;
		gap: 5px;
		padding: 3px 8px 3px 5px;
		border-radius: 999px;
		background: linear-gradient(135deg, #7c3aed 0%, #4f46e5 100%);
		color: #fff;
		font-size: 10px;
		font-weight: 700;
		letter-spacing: .25px;
		box-shadow: 0 4px 10px rgba(79, 70, 229, .28);
		border: 1px solid rgba(255,255,255,.22);
	}
	.king-player-badge .king-badge-icon{
		width: 18px;
		height: 18px;
	}
	.king-player-badge .king-badge-crown{
		width: 11px;
		height: 11px;
	}
	.king-table-id{
		display: inline-block;
		margin-top: 3px;
		font-size: 10px;
		color: #64748b;
		font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
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