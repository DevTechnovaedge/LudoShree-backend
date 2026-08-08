@extends('layouts.app')

@section('content')
<div class="container my-5">
    <h2 class="text-center mb-4">Contact Us</h2>
    <div class="row">
      <!-- Phone Section -->
      <!-- <div class="col-md-4 text-center">
        <i class="fas fa-phone fa-3x mb-3 text-primary"></i>
        <h4>Phone</h4>
        <p>{{ site_setting()->mobile }}</p>
      </div> -->
      <!-- Email Section -->
      <div class="col-md-6 text-center">
        <i class="fas fa-envelope fa-3x mb-3 text-primary"></i>
        <h4>Email</h4>
        <p>{{ site_setting()->email }}</p>
      </div>
      <!-- Address Section -->
      <div class="col-md-6 text-center">
        <i class="fas fa-map-marker-alt fa-3x mb-3 text-primary"></i>
        <h4>Address</h4>
        <p>Jaipur, Rajasthan</p>
      </div>
    </div>
  </div>
@endsection