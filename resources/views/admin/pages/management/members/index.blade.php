@extends('admin.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card m-4">
                <div class="card-header bg-theme">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="m-0">Members</h5>
                        </div>
                        @can('permissions', ['members', 'create'])
                        <div class="col-md-6 text-right">
                            <a href="{{ route('admin::members.create') }}" class="btn btn-warning btn-sm">Add</a>
                        </div>
                        @endcan
                    </div>
                </div>
                <div class="card-body">

                    @if(session()->has('back_msg'))
                        {!! session()->get('back_msg') !!}
                    @endif

                    <table id="example" class="table table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Role Type</th>
                                <th class="text-center">Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($members as $member)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $member->name }}</td>
                                <td>{{ $member->role_type }}</td>
                                <td class="text-center">{!! $member->status_view !!}</td>
                                <td class="d-flex">
                                    @if($member->id != 1)
                                        @can('permissions', ['members', 'create'])
                                        <a href="{{ route('admin::members.edit', $member->id) }}" class="px-2"><i class="fa fa-edit"></i></a>
                                        @endcan

                                        @can('permissions', ['members', 'create'])
                                        <form action="{{ route('admin::members.destroy', $member->id) }}" method="post" onsubmit="return confirm('Are you sure')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="px-2 text-danger border-0 bg-transparent">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </form>
                                        @endcan
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content_js')

<script>
    $(document).ready(function() {
        $('#example').DataTable();
    });
</script>

@endsection