@extends('admin.app')

@section('content')


<!-- Main content -->
<section class="content">
	<div class="container">
		<div class="row">
			<!-- left column -->
			<div class="col-md-12">

				<!-- general form elements -->
				<div class="card  mt-5">
					@if(session()->has('back_msg'))
					{!! session()->get('back_msg') !!}
					@endif

					<div class="card-header  bg-theme">
					<div class="row align-items-center">
							<div class="col-sm-6">
								<h5 class="m-0">Add Slider</h5>
							</div><!-- /.col -->
							<div class="col-sm-6 text-right">
								<a href="{{ url('admin/sliders') }}" class="btn btn-sm btn-warning">List</a>
							</div><!-- /.col -->
						</div><!-- /.row -->
					</div>

					<div class="card-body">


						<!-- form start -->
						<form method="post" action="{{ route('admin::sliders.update',$slider->id) }}" autocomplete="off" enctype="multipart/form-data">
							@csrf
							@method('PUT')

							<div class="card-body row">

								<div class="form-group col-md-4">
									<label for="slider_name">Slider Name</label>
									<input type="text" class="form-control" id="name" name="name" placeholder="" value="{{ old('name',$slider->name) }}" required="" onkeyup="makeurl(this.value)" onblur="makeurl(this.value)">
								</div>

								<div class="form-group col-md-4">
									<label for="slider_name">Slider Code</label>
									<input type="text" class="form-control" placeholder="" value="{{ old('code',$slider->code) }}" readonly>
								</div>

								<div class="form-group col-md-6 d-none">
									<label for="sku">Slider Class</label>
									<input type="text" class="form-control" id="class" name="class" placeholder="" value="{{ old('class',$slider->class) }}">
								</div>

								<div class="form-group col-md-6 d-none">
									<label for="status">Type</label>
									<select type="text" class="form-control" id="type" name="type" required="">
										<option value="">Select Type</option>
										<option value="1" {{ (old('type',$slider->type)=="1" || old('type')==null)?'selected':'' }}>Full Width Slider</option>
										<!--  <option value="2" {{ (old('type',$slider->type)=="2")?'selected':'' }}>Event Slider</option> -->
									</select>
								</div>

								<div class="form-group col-md-4">
									<label for="status">Status</label>
									<select type="text" class="form-control" id="status" name="status" required="">
										<option value="">Select Status</option>
										<option value="1" {{ (old('status',$slider->status)=="1" || old('status')==null)?'selected':'' }}>Active</option>
										<option value="0" {{ (old('status',$slider->status)=="0")?'selected':'' }}>Deactive</option>
									</select>
								</div>

								<div class="col-md-12">
									<div class="text-center">
										<button type="submit" class="btn btn-sm btn-primary">Update</button>
									</div>
								</div>
							</div>

						</form>
					</div>
				</div>
				<!-- /.card -->
			</div>
		</div>
	</div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
<script src="https://cdn.ckeditor.com/4.14.0/standard/ckeditor.js"></script>
<script>
	function makeurl(val) {
		var string = val.toLowerCase().replace(/[^\w\s]/gi, '');
		document.getElementById("code").value = string.replace(/\s/g, '-');
	}
	$("#category_ids").select2();
	$('.ckeditor').ckeditor();

	function deleteGalleryImage(th, id = '') {
		if (id == '') {
			$(th).parent().parent().remove();
		} else {
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
					deleteSliderGalleryImage(th, id);
				}
			});
		}
	}

	function deleteSliderGalleryImage(th, id = '') {
		$.ajax({
			url: "{{ url('admin/sliders/deleteSliderGalleryImage') }}",
			type: 'post',
			data: {
				"id": id,
				"_token": "{{ csrf_token() }}"
			},
			beforeSend: function() {},
			success: function(response) {
				$(th).parent().remove();
				Swal.fire(
					'Deleted!',
					'Image has been deleted.',
					'success'
				);
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

	function changeAttributeSet() {
		var attribute_set_id = $("#attribute_set_id").val();
		var slider_id = "{{ $slider->id }}";

		$.ajax({
			url: "{{ url('admin/sliders/getSliderAttributeFields') }}",
			type: 'post',
			data: {
				"attribute_set_id": attribute_set_id,
				"slider_id": slider_id,
				"_token": "{{ csrf_token() }}"
			},
			beforeSend: function() {},
			success: function(response) {
				$(".slider_attributes").html(response);
			},
			error: function() {}
		});
	}
</script>
@endsection