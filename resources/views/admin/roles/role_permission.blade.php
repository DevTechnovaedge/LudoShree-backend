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
						<div class="row align-self-center">
							<div class="col-sm-6">
								<h5 class="m-0">Role: {{ $role->name }}</h5>
							</div><!-- /.col -->
						</div><!-- /.row -->
					</div>


					<div class="card-body">



						<!-- form start -->
						<form method="post" action="{{ route('admin::role_permission_update') }}" autocomplete="off">
							@csrf
							<input type="hidden" id="role_id" name="role_id" value="{{ $role->id }}">

							<div class="card-body row">

								<div class="form-group col-md-12">
									<h4>Permissions</h4>
								</div>

								<div class="form-group col-md-12">

									<div class="table-responsive">
										<table class="table table-bordered" id="empTable">
											<thead>
												<tr>
													<th style="width: 30px;">M.Id</th>
													<th>Module Name</th>
													<th class="nosort wd-50 text-center">Create</th>
													<th class="nosort wd-50 text-center">Edit</th>
													<th class="nosort wd-50 text-center">View</th>
													<th class="nosort wd-50 text-center">Delete</th>
												</tr>
											</thead>

											<tbody>
												<?php foreach ($module_list as $module) { ?>
													<tr>
														<td class="text-center"><?= $module->m_id ?></td>
														<td><?= $module->module_name ?></td>
														<td class="text-center">
															<input type="hidden" name="module_id[<?= $module->m_id ?>]" value="<?= $module->m_id ?>">
															<?php if ($module->perm_create) { ?>
																<input type="checkbox" name="module[<?= $module->m_id ?>][rr_create]" value="1" <?php if ($module->rr_create) {
																																					echo 'checked';
																																				} ?>>
															<?php } ?>
														</td>
														<td class="text-center">
															<?php if ($module->perm_edit) { ?>
																<input type="checkbox" name="module[<?= $module->m_id ?>][rr_edit]" value="1" <?php if ($module->rr_edit) {
																																					echo 'checked';
																																				} ?>>
															<?php } ?>
														</td>
														<td class="text-center">
															<?php if ($module->perm_view) { ?>
																<input type="checkbox" name="module[<?= $module->m_id ?>][rr_view]" value="1" <?php if ($module->rr_view) {
																																					echo 'checked';
																																				} ?>>
															<?php } ?>
														</td>
														<td class="text-center">
															<?php if ($module->perm_delete) { ?>
																<input type="checkbox" name="module[<?= $module->m_id ?>][rr_delete]" value="1" <?php if ($module->rr_delete) {
																																					echo 'checked';
																																				} ?>>
															<?php } ?>
														</td>
													</tr>
												<?php } ?>
											</tbody>

										</table>
									</div>

									<div class="text-right">
		
											<button type="submit" class="bg-theme btn btn-lg mt-2">Update</button>
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