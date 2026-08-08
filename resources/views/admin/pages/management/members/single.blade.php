@extends('admin.app')

@section('content')

<section class="content">
  <div class="container">
    <div class="row">
      <div class="col-md-12">
        <div class="card mt-4">
          <div class="card-header bg-theme">
            <div class="row align-items-center">
              <div class="col-md-6">
                <h5 class="m-0">Add / Edit Member</h5>
              </div>
              <div class="col-md-6 text-right">
                <a href="{{ route('admin::members.index') }}" class="btn btn-warning btn-sm text-white">Members</a>
              </div>
            </div>
          </div>
          <div class="card-body p-4">
          <form action="{{ route('admin::members.store') }}" method="post">
            @csrf
            <input type="hidden" name="id" value="{{ $member->id ?? '' }}">
            <div class="row">

              <div class="col-md-6">
                <div class="form-group">
                  <label for="">Role</label>
                  <select type="text" name="role_id" class="form-control">
                    @foreach ($roles as $role)
                      <option value="{{ $role->id }}" {{ isset($member) && $member->role_id == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                    @endforeach
                  </select>
                </div>
              </div>
             
              <div class="col-md-6">
                <div class="form-group">
                  <label for="">Name</label>
                  <input type="text" name="name" placeholder="Enter name" value="{{ $member->name ?? '' }}" class="form-control">
                  @error('name')  <sapn class="text-danger">{{ $message }}</span> @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group">
                  <label for="">Email</label>
                  <input type="email" name="email" placeholder="Enter email" value="{{ old('email', $member->email ?? '') }}" class="form-control">
                  @error('email')  <sapn class="text-danger">{{ $message }}</span> @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group">
                  <label for="">Mobile</label>
                  <input type="text" name="mobile" placeholder="Enter mobile" value="{{ $member->mobile ?? '' }}" class="form-control">
                  @error('mobile')  <sapn class="text-danger">{{ $message }}</span> @enderror
                </div>
              </div>

              <div class="col-md-6">
                <div class="form-group">
                  <label for="">New Password</label>
                  <input type="hidden" name="old_password" value="{{ $member->password ?? '' }}">
                  <input type="password" name="password" placeholder="Enter password" class="form-control">
                </div>
              </div>
            
              <div class="col-md-6">
                <div class="form-group">
                  <label for="">Status</label>
                  <select type="text" name="status" class="form-control">
                    <option value="1" {{ isset($member) && $member->status == 1 ? 'select' : '' }}>Active</option>
                    <option value="0" {{ isset($member) && $member->status == 0 ? 'select' : '' }}>Deactive</option>
                  </select>
                </div>
              </div>

              <div class="col-md-12">
                <div class="text-center mt-2">
                  <input type="submit" class="btn btn-primary">
                </div>
              </div>
            </div>
          </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

@endsection

@section('content_js')
<script>
    function getPropertiesViaCategory(category_id){
           $.ajax({
                type            :   "POST",
                url             :   "{{ url('admin/getPropertiesViaCategory') }}",
                dataType        :   'json',
                data            :   { "_token": "{{ csrf_token() }}", category_id : category_id },
                success         :   function (data) {
                                                        if(data.status){
                                                            $('.properties').html(data.options);
                                                            $('.properties-col').removeClass('d-none');
                                                        }else{
                                                            $('.properties-col').addClass('d-none');
                                                        }
                                                    },
                error           :   function () {
                                                   alert('Some Error Occured.');
                                                }
        });
    }
</script>
@endsection