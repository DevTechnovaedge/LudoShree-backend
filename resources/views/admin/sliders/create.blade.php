@extends('admin.app')

@section('content')


<!-- Main content -->
<section class="content">
	<div class="container">
		<div class="row">
			<!-- left column -->
			<div class="col-md-12">

				<!-- general form elements -->
				<div class="card mt-4">

					@if(session()->has('back_msg'))
					{!! session()->get('back_msg') !!}
					@endif

					<div class="card-header bg-theme">
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
						<form method="post" action="{{ route('admin::sliders.store') }}" autocomplete="off" enctype="multipart/form-data">
							@csrf
							<div class="card-body row">

								<div class="form-group col-md-4">
									<label for="slider_name">Slider Name <span class="text-danger">*</span></label>
									<input type="text" class="form-control" id="name" name="name" placeholder="Enter slider name" value="{{ old('name') }}" required="" onkeyup="makeurl(this.value)" onblur="makeurl(this.value)">
								</div>

								<div class="form-group col-md-4">
									<label for="slider_name">Slider Code</label>
									<input type="text" class="form-control" id="code" name="code" placeholder="Enter slider code" value="{{ old('code') }}" required="">
								</div>

								<div class="form-group col-md-6 d-none">
									<label for="sku">Slider Class</label>
									<input type="text" class="form-control" id="class" name="class" placeholder="Enter slider class" value="{{ old('class') }}">
								</div>

								<div class="form-group col-md-6  d-none">
									<label for="status">Type</label>
									<select type="text" class="form-control" id="type" name="type" required="">
										<option value="">Select Type</option>
										<option value="1" {{ (old('type')=="1" || old('type')==null)?'selected':'' }}>Full Width Slider</option>
										<!-- <option value="2" {{ (old('type')=="2")?'selected':'' }}>Event Slider</option> -->
									</select>
								</div>

								<div class="form-group col-md-4">
									<label for="status">Status</label>
									<select type="text" class="form-control" id="status" name="status" required="">
										<option value="">Select Status</option>
										<option value="1" {{ (old('status')=="1" || old('status')==null)?'selected':'' }}>Active</option>
										<option value="0" {{ (old('status')=="0")?'selected':'' }}>Deactive</option>
									</select>
								</div>

								<div class="col-md-12">
									<div class="text-center">
										<button type="submit" class="btn btn-sm btn-primary">Submit</button>
									</div>
								</div>
							</div>
							<!-- /.card-body -->

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

	function changeAttributeSet() {
		var attribute_set_id = $("#attribute_set_id").val();
		var slider_id = "";

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