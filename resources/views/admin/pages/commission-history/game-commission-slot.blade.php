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
								<h5 class="m-0"> Game Commission Slot </h5>
							</div>
						</div>
					</div>
					<div class="card-body p-4">
					@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif
						<form id="game-commission-slot-form" method="post" action="{{ url('admin/update-game-commission-slot') }}">
							@csrf
							<input type="hidden" value="1" name="id">
							<!-- Table View -->
							 <table class="table table-bordered">
								<tr>
									<th>#</th>
									<th>Name</th>
									<th>Update Information ( % )</th>
									<th>Description</th>
								</tr>
								<tr>
									<td>1</td>
									<td>Slab For 1 - 99</td>
									<td>
										<input type="text" class="form-control" name="slot_1_to_99" value="{{ $record->slot_1_to_99 }}">
										@error('slot_1_to_99') <div class="text-danger">{{ $message }}</div> @enderror
									</td>
									<td>Update Value more then 0 to apply</td>
								</tr>
								<tr>
									<td>2</td>
									<td>Slab For 100 - 499</td>
									<td>
										<input type="text" class="form-control" name="slab_100_to_499" value="{{ $record->slab_100_to_499 }}">
										@error('slab_100_to_499') <div class="text-danger">{{ $message }}</div> @enderror
									</td>
									<td>Update Value more then 0 to apply</td>
								</tr>
								<tr>
									<td>3</td>
									<td>Slab For 500 - Above</td>
									<td>
										<input type="text" class="form-control" name="slab_500_to_above" value="{{ $record->slab_500_to_above }}">
										@error('slab_500_to_above') <div class="text-danger">{{ $message }}</div> @enderror
									</td>
									<td>Update Value more then 0 to apply</td>
								</tr>

								<tr>
									<td>4</td>
									<td>Refer Commission</td>
									<td>
										<input type="text" class="form-control" name="refer_commission" value="{{ $record->refer_commission }}">
										@error('refer_commission') <div class="text-danger">{{ $message }}</div> @enderror
									</td>
									<td>Update Value more then 0 to apply</td>
								</tr>

							 </table>
							<!-- End Table View -->
								<div class="text-center">
									<button class="btn btn-sm bg-theme">Submit</button>
								</div>
						</form>
					</div>
				</div>
				<!-- End User Commission Management -->

			</div>
		</div>

	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
@endsection