@extends('admin.app')

@section('style')
<style>
  .dashboard-card-old {
    height: 110px;
    align-content: space-around;
    border-radius: 8px;
    margin: 0;
  }

  .dashboard-card {
    align-content: space-around;
    border-radius: 0;
    margin: 0;
  }

  .dashboard-card .card-body {
    align-content: space-around;
    padding: 0 !important;
  }

  .dashboard-card .records-count-wrapper {
    padding: 1rem;
  }

  .dashboard-card .fa {
    font-size: 34px;
  }

  .dashboard-card .total-count-container {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid #808080ab;
    padding: 5px 10px;
  }

  .records-count {
    font-size: 1rem;
    font-weight: 900;
    color: #fff;
  }

  .records-count+h6 {
    font-size: 13px;
  }
</style>
@endsection

@section('content')
<!-- Content Header (Page header) -->
<div class="content-header">
  <div class="container-fluid">
    @livewire('admin.totalBusinessStatics')
    @livewire('admin.todayBusinessStatics')
  </div><!-- /.container-fluid -->
</div>
<!-- /.content-header -->

@endsection