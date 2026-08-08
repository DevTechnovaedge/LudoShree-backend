@extends('layouts.app')

@section('content')
@php
$download_url = url('download-apk');
$refer_code_segment = '';

if(request()->referCode):
    $download_url .= "?referCode=".request()->referCode;
    $refer_code_segment .= "?referCode=".request()->referCode;
endif;
@endphp

<main>
    <!-- Hero / Banner Section -->
    <section class="banner_section py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-12">
                    <h1>Welcome to Our Store</h1>
                    <p>Discover amazing products and download our app for the best shopping experience.</p>

                    <!-- Buttons -->
                    <ul class="app_btn list-unstyled d-flex gap-2 mt-3">
                        <li>
                            <a href="{{ url('play-online') }}" class="btn btn-outline-primary">Shop Now</a>
                        </li>
                        <li>
                            <a href="{{ $download_url }}" class="btn btn-primary">
                                Download App
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Product Section -->
    <section class="products py-5">
        <div class="container">
            <h2 class="text-center mb-4">Featured Products</h2>
            <div class="row">
                <!-- Example product card -->
                <div class="col-md-3 mb-4">
                    <div class="card h-100">
                        <img src="{{ asset('assets/images/product1.png') }}" class="card-img-top" alt="Product">
                        <div class="card-body">
                            <h5 class="card-title">Product Name</h5>
                            <p class="card-text">₹499</p>
                            <a href="#" class="btn btn-success">Buy Now</a>
                        </div>
                    </div>
                </div>
                <!-- Repeat product cards as needed -->
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="review_section py-5 bg-light">
        <div class="container">
            <h2 class="text-center mb-4">Customer Reviews</h2>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card p-3">
                        <h5>John Doe</h5>
                        <p>⭐⭐⭐⭐⭐</p>
                        <p>Great shopping experience! Highly recommend.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card p-3">
                        <h5>Jane Smith</h5>
                        <p>⭐⭐⭐⭐⭐</p>
                        <p>Fast delivery and good quality products.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card p-3">
                        <h5>Michael Lee</h5>
                        <p>⭐⭐⭐⭐⭐</p>
                        <p>Easy to use app and great products!</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
