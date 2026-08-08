@extends('admin.app')

@section('content')
<section class="content">
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card mt-5">
                <div class="card-header bg-theme">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h5 class="m-0">Contact Enquires</h5>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <table id="example" class="table table-bordered" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Mobile</th>
                                <th>Course</th>
                                <th>Address</th>
                                <th>Message</th>
                                <th>Created date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records ?? [] as $record)
                            
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $record->name }}</td>
                                <td>{{ $record->email }}</td>
                                <td>{{ $record->mobile }}</td>
                                <td>{{ $record->course }}</td>
                                <td>{{ $record->address }}</td>
                                <td>{{ $record->message }}</td>
                                <td>{{ $record->created_at }}</td>
                               
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</section>
@endsection

@section('content_js')

<script>
    $(document).ready(function() {
        $('#example').DataTable();
    });
</script>

@endsection