@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-md-12">
            <h2 class="text-center my-3">{{ $page->title }}</h2>
        </div>
        <div class="col-md-12">
            <div class="content">{!! $page->content !!}</div>
        </div>
    </div>
@endsection