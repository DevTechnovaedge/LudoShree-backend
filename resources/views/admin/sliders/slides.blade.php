@extends('admin.app')

@section('style')
<style>
	td {
		vertical-align: middle !important;
	}
</style>
@endsection

@section('content')

<!-- Main content -->
<section class="content">
	<div class="container-fluid">
		<div class="row">
			<div class="col-12">
				<div class="card mt-4">
					<div class="card-header bg-theme">
						<div class="row  align-items-center">
							<div class="col-md-8">
								<h5 class="m-0">Slide List
									<small class="text-warning">( {{ $slider->name }} )</small>
								</h5>
							</div><!-- /.col -->
							<div class="col text-right">
								@if(count($sliders)>0)
									<button type="button" class="btn btn-info btn-sm" onclick="updateSortOrder()">Update Sort Order</button>
								@endif
							</div>
							<div class="col-1 text-right">
								<a href="{{ url('admin/sliders/'.$slider->id.'/add_slide') }}" class="btn btn-warning btn-sm">Add Slide</a>
							</div><!-- /.col -->
						</div><!-- /.row -->
					</div>

					<div class="card-body">
						@if(session()->has('back_msg'))
						{!! session()->get('back_msg') !!}
						@endif
						<table id="example2" class="table table-bordered table-hover">
							<thead>
								<tr>
									<th style="width: 50px;" class="text-center">Sort</th>
									<th style="width: 50px;" class="text-center">ID</th>
									<th>Heading</th>
									<th class="text-center">Slide Image</th>
									<th class="text-center">Mobile Slide Image</th>

									<th style="width: 80px;" class="text-center">Sort Order</th>
									<th class="text-center">Status</th>
									<th style="width: 100px;" class="text-center">Action</th>
								</tr>
							</thead>
							<tbody id="sortable">

								<?php foreach ($sliders as $key => $row) { ?>
									<tr>
										<td class="text-center"><span class='ui-state-default' style="border: none;"><i class='fa fa-th'></i></span></td>
										<td class="text-center">{{ ++$key }} <input type="hidden" class="sort_ids" name="ids[]" value="{{ $row->id }}"></td>
										<td>{{ $row->heading }} <input type="hidden" class="sort_ids" name="ids[]" value="{{ $row->id }}"></td>
										<td class="text-center">
											@if($row->image)
											<a href="{{ $row->image_url }}" target="_blank"><img src="{{ $row->image_url }}" class="form-img"></a>
											@endif
										</td>
										<td class="text-center">
											@if($row->mobile_image)
											<a href="{{ $row->mobile_image_url }}" target="_blank"><img src="{{ $row->mobile_image_url }}" class="form-img"></a>
											@endif
										</td>
										<td class="text-center">{{ $row->sort_order }}</td>
										<td class="text-center">{!! $row->status_view !!}</td>
										<td class="text-center">
											@can('permissions', ['sliders', 'edit'])
											<a href="{{ url('admin/sliders/'.$slider->id.'/edit-slide/'.$row->id) }}" class="btn btn-success btn-sm"><i class="fa fa-edit"></i></a>
											@endcan

											@can('permissions', ['sliders', 'delete'])
											<form action="{{ route('admin::sliders.deleteSlide',$row->id) }}" method="post" class="d-inline">
												@csrf
												@method('DELETE')
												<button type="button" class="btn btn-danger btn-sm ml-2" onclick="deleteRecord(this)"><i class="fa fa-trash"></i></button>
											</form>
											@endcan
										</td>
									</tr>
								<?php } ?>

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

	$("#sortable").sortable();

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

	function updateSortOrder() {

		var sort_ids = $('.sort_ids').map(function() {
			return $(this).val();
		}).get().join();

		$.ajax({
			url: "{{ url('admin/slides/updateSortOrder') }}",
			type: 'post',
			data: {
				"id": '{{ $slider->id }}',
				"sort_ids": sort_ids,
				"_token": "{{ csrf_token() }}"
			},
			beforeSend: function() {},
			success: function(response) {
				window.location.href = '';
			},
			error: function() {
				Swal.fire(
					'Error!',
					'Some error occured.',
					'error'
				);
			}
		});

	}
</script>
@endsection