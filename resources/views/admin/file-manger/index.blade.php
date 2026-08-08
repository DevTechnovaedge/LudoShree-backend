@extends('admin.app')


@section('content')

<!-- Main content -->
<section class="content">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-12">
                <h4>📂 Current Directory: {{ str_replace('public/', '', $directory) }}</h4>
                <form action="#" method="POST">
                    @csrf

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Select</th>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Created Date</th>
                                <th>Modified Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Back Button -->
                            @if($directory !== 'public')
                            <tr>
                                <td>🔙</td>
                                <td>
                                    <a href="{{ url('admin/storage-folders-and-files/public/' . dirname(str_replace('public/', '', $directory))) }}">
                                        Go Back
                                    </a>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            @endif
                            <!-- Folders -->
                            @foreach($folders as $folder)
                            <tr>
                                <td><input type="checkbox" name="folders[]" value="{{ $folder }}"></td>
                                <td>
                                    <a href="{{ url('admin/storage-folders-and-files/'.$folder) }}">
                                        📁 {{ str_replace($directory . '/', '', $folder) }}
                                    </a>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                            @endforeach

                            <!-- Files -->
                            @foreach($files as $file)
                            <tr>
                                <td>
                                    <input type="checkbox" name="files[]" value="{{ $file }}">
                                </td>
                                <td>{{ basename($file) }}</td>
                                <td>
                                </td>
                                <td>
                                </td>
                                <td>
                                </td>
                                <td>
                                    <a href="{{ asset('storage/'.str_replace('public/', '', $file)) }}" target="_blank" class="btn btn-primary btn-sm">View</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <button type="submit" class="btn btn-danger">Delete Selected</button>
                </form>
            </div>
        </div>
    </div>
</section>

@endsection