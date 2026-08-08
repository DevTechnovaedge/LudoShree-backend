@extends('admin.app')

@php


    $country    = $record->aadhaar_card_details->data->data->address->country ?? "";
    $dist        = $record->aadhaar_card_details->data->data->address->dist ?? "";
    $state        = $record->aadhaar_card_details->data->data->address->state ?? "";
    $po            = $record->aadhaar_card_details->data->data->address->po ?? "";
    $loc        = $record->aadhaar_card_details->data->data->address->loc ?? "";
    $street        = $record->aadhaar_card_details->data->data->address->street ?? "";
    $house        = $record->aadhaar_card_details->data->data->address->house ?? "";
    $landmark   = $record->aadhaar_card_details->data->data->address->landmark ?? "";
    $aadhar_address    = "$house, $street, $loc, $po, $state, $dist";

    $verify_aadhaar_number    = $record->aadhaar_card_details->data->data->aadhaar_number ?? 0;
    $verify_aadhaar_dob    = $record->aadhaar_card_details->data->data->dob ?? 0
@endphp

@section('content')
<style>
    span.select2-selection.select2-selection--single {
        height: 37px;
        padding: 6px;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 5px;
        right: 5px;
    }

    .btn-hide-show {
        cursor: pointer;
    }
    
    .game-challenge-action-btn{
        display:none;
    }

  .text-line-through {
    text-decoration: line-through;
  }
</style>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12 mx-auto">
                <div class="card mt-4">
                    <div class="card-header bg-theme">
                        <div class="row">
                            <div class="col-sm-6 text-left align-self-center">
                                <h5 class="m-0">{{ $shared_data->page_title }}</h5>
                            </div>
                            @can('permissions', [ $shared_data->permission_key, 'create' ] ?: [])
                            <div class="col-sm-6 text-right">
                                <a class="btn btn-warning" href="{{ route($shared_data->index_route) }}">List</a>
                            </div>
                            @endcan
                        </div>
                    </div>
                    <div class="card-body p-4">
                        @if(session()->has('back_msg'))
                        {!! session()->get('back_msg') !!}
                        @endif
                        <div class="row">
                            <!-- left column -->
                            <div class="col-md-12">
                                <!-- form start -->
                                <form method="post" action="{{ route($shared_data->store_route) }}" enctype="multipart/form-data">
                                    @csrf
                                    <input type="hidden" name="id" value="{{ $record->id ?? '' }}">
                                    <input type="hidden" name="uid" value="{{ $record->uid ?? '' }}">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <h6 class="m-0">Basic Details
                                                @if($record->updated_at ?? 0)
                                                <small><i>( Last Update : {{ $record->updated_at }} )</i></small>
                                                @endif
                                            </h6>
                                        </div>
                                        <!-- Status -->
                                        
                                        <div class="col-2">
                                            <div class="form-group m-0 text-right">
                                                <label for="name">Status</label>
                                                {!! $record->status_view ?? '' !!}
                                            </div>
                                        </div>
                                        <!-- End Status -->

                                        <!-- KYC Status -->
                                        <div class="col-2 ">
                                            <div class="form-group m-0  text-right">
                                                <label for="name">KYC Status</label>
                                                {!! $record->kyc_status_view ?? '' !!}
                                            </div>
                                        </div>
                                        <!-- End KYC Status -->

                                        <!-- Withdrawal Status -->
                                        <div class="col-2">
                                            <div class="form-group m-0  text-right">
                                                <label for="name">Withdrawal Status</label>
                                                {!! $record->withdrawal_status_view ?? '' !!}
                                            </div>
                                        </div>
                                        <!-- End Withdrawal Status -->
                                        <div class="col-md-12">
                                        <hr style="border-top: 3px solid #646464c2;">
                                    </div>

                                        <!-- UID -->
                                        @if($record->uid ?? 0)
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="refer_code">UID</label>
                                                <input type="text" class="form-control" value="{{ old('uid', $record->uid ?? '') }}" readonly>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- End UID -->

                                        <!-- Refer Code -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="refer_code">Refer Code</label>
                                                <input type="text" class="form-control" id="refer_code" name="refer_code" placeholder="Enter refer code" value="{{ old('refer_code', $record->refer_code ?? '') }}" readonly>
                                                @error('refer_code') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Refer Code -->

                                        <!-- Name -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="name">Name <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="name" name="name" placeholder="Enter name" value="{{ old('name', $record->name ?? '') }}" required>
                                                @error('name') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Name -->

                                        <!-- Mobile -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="mobile">Mobile <span class="text-danger">*</span></label>
                                                <input type="text" class="form-control" id="mobile" name="mobile" placeholder="Enter mobile" value="{{ old('mobile', $record->mobile ?? '') }}" required>
                                                @error('mobile') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Mobile -->

                                        <!-- Email -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="email">Email</label>
                                                <input type="text" class="form-control" id="email" name="email" placeholder="Enter email" value="{{ old('email', $record->email ?? '') }}">
                                                @error('email') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Email -->

                                        <!-- Profile -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="profile">Profile

                                                    @if(isset($record) && $record->profile != '')
                                                    <input type="hidden" name="old_profile" value="{{ $record->profile }}">
                                                    <small class="ml-1">( <a href="{{ $record->profile_url }}" alt="" class="mt-2" target="_blank">View</a> )</small>
                                                    @endif
                                                </label>
                                                <input type="file" class="form-control p-1" id="profile" name="profile">
                                                @error('profile') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Profile -->

                                        <!-- Date of Birth -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="dob">DOB</label>
                                                <input type="date" class="form-control" id="dob" name="dob" value="{{ old('dob', $record->dob ?? '') }}">
                                                @error('dob') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Date of Birth -->

                                        <!-- State -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="state">State</label>
                                                <select type="text" class="form-control" id="state_id" name="state_id">
                                                    <option value="" disabled selected>Choose...</option>
                                                    @foreach(App\Models\State::get() ?? [] as $state)
                                                    <option value="{{ $state->id }}" {{ (old('state_id', $record->state_id ?? '') == $state->id) ? 'selected' : '' }}>{{ $state->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End State -->

                                        <!-- Sponsor -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="sponsor">Sponsor</label>
                                                <select name="sponsor_id" id="sponsor" class="form-control select2">
                                                    <option value="" selected disabled>Choose...</option>
                                                    <option value="0" {{ ( $record->refer_by ?? 0 ) == 0 ? 'selected' : '' }}>None</option>
                                                    @foreach(($sponsorUsers ?? collect()) as $refer_user)
                                                    <option value="{{ $refer_user->id }}" {{ ( $record->refer_by ?? 0 ) == $refer_user->id ? 'selected' : '' }}>{{ $refer_user->name }} <small>(UID: {{ $refer_user->uid }})</small></option>
                                                    @endforeach
                                                </select>
                                                @error('sponsor') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Sponsor -->

                                        <!-- Refer Income -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="refer_income">Refer Income </label>
                                                <select type="text" class="form-control" id="refer_income" name="refer_income">
                                                    <option value="" disabled selected>Choose...</option>
                                                    <option value="0" {{ ( old('refer_income', $record->refer_income ?? '') == 0 ) ? 'selected' : '' }}>Inactive</option>
                                                    <option value="1" {{ ( old('refer_income', $record->refer_income ?? '') == 1 ) ? 'selected' : '' }}>Win</option>
                                                    
                                                    <!-- <option value="2" {{ ( old('refer_income', $record->refer_income ?? '') == 2 ) ? 'selected' : '' }}>Game</option> -->
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End Refer Income -->

                                        <!-- Status -->

                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="status">Status <span class="text-danger">*</span></label>
                                                <select type="text" class="form-control {{ (($record->status ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="status" name="status" required>
                                                    <option value="" disabled selected>Choose...</option>
                                                    <option value="1" {{ ( old('status', $record->status ?? '') == 1 ) ? 'selected' : '' }}>Active</option>
                                                    <option value="0" {{ ( old('status', $record->status ?? '') == 0 ) ? 'selected' : '' }}>Deactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End Status -->

                                        <!-- Withdrawal -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="withdrawal_status">Withdrawal Status <span class="text-danger">*</span></label>
                                                <select type="text" class="form-control {{ (($record->withdrawal_status ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="status" name="withdrawal_status" required>
                                                    <option value="" disabled selected>Choose...</option>
                                                    <option value="1" {{ ( old('withdrawal_status', $record->withdrawal_status ?? '') == 1 ) ? 'selected' : '' }}>Active</option>
                                                    <option value="0" {{ ( old('withdrawal_status', $record->withdrawal_status ?? '') == 0 ) ? 'selected' : '' }}>Inactive</option>
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End Withdrawal -->

                                        <!-- Cashier Role -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="is_cashier">Cashier (Withdrawal Panel)</label>
                                                <select type="text" class="form-control {{ (($record->is_cashier ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="is_cashier" name="is_cashier">
                                                    <option value="0" {{ ( old('is_cashier', $record->is_cashier ?? 0) == 0 ) ? 'selected' : '' }}>No</option>
                                                    <option value="1" {{ ( old('is_cashier', $record->is_cashier ?? 0) == 1 ) ? 'selected' : '' }}>Yes</option>
                                                </select>
                                                <small class="text-muted">Allow login on LudoShreeCashier app to handle withdrawals.</small>
                                            </div>
                                        </div>
                                        <!-- End Cashier Role -->

                                        
                                        <!-- Remark -->
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="remark">Remark</label>
                                                <textarea type="text" class="form-control" id="remark" name="remark" placeholder="Enter remark">{{ old('name', $record->remark ?? '') }}</textarea>
                                                @error('remark') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Remark -->

                                        <!-- Game Details -->
                                        <!-- Game Details -->
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Game Details</h6>
                                            </div>
                                            <hr style="border-top: 3px solid #646464c2;">
                                        </div>

                                        <div class="col-md-12" id='game-detials-wrapper'>
                                            <div class="row">
                                                <div class="col-md-6">

                                                    <div class="table-responsive">
                                                        <table class="table table-bordered">
                                                            <tr>
                                                                <td>Game Wallet</td>
                                                                <td>{{ number_format($record->game_wallet_amount ?? 0, 2, '.', '') }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Win Wallet</td>
                                                                <td>{{ number_format($record->win_wallet_amount ?? 0, 2, '.', '')}}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Refer Amount</td>
                                                                <td>{{ number_format($record->refer_wallet_amount ?? 0, 2, '.', '') }}</td>
                                                                <!-- <td>{{ $record->refer_commission_amount_sum ?? 0 }}</td> -->
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </div>


                                                <div class="col-md-6">
                                                    <div class="table-responsive">
                                                        <table class="table table-bordered">
                                                            <tr>
                                                                <td>Deposit</td>
                                                                <td>{{ number_format($record->deposit_sum_amount ?? 0, 2, '.', '') }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Withdrawal</td>
                                                                <td>{{ number_format($record->withdrawal_sum_amount ?? 0, 2, '.', '') }}</td>
                                                            </tr>
                                                            <tr>
                                                                <td>Balance</td>
                                                                <td>{{ number_format($record->total_wallet_amount ?? 0, 2, '.', '') }}</td>
                                                            </tr>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Game Details -->
                                        <!-- End Game Details -->

                                        <!-- KYC -->
                                        <div class="col-md-12">
                                            <h6 class="mt-3">KYC Details</h6>
                                            <hr style="border-top: 3px solid #646464c2;">
                                        </div>

                                        <!-- Documents -->
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="documents">Document Type</label>
                                                <select type="text" class="form-control" id="document_type_id" name="document_type_id">
                                                    <option value="" disabled selected>Choose...</option>
                                                    @foreach(App\Models\DocumentType::get() ?? [] as $document_type)
                                                    <option value="{{ $document_type->id }}" {{ (old('document_type_id', $record->document_type_id ?? '') == $document_type->id) ? 'selected' : '' }}>{{ $document_type->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End Documents -->

                                        <!-- Document ID -->
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="name">Document ID</label>
                                                <input type="text" class="form-control" id="document_id" name="document_id" placeholder="Enter document id" value="{{ old('document_id', $record->document_id ?? '') }}">
                                                @error('document_id') <div class="text-danger">{{ $message }}</div> @enderror
                                            </div>
                                        </div>
                                        <!-- End Document ID -->

                                        <!-- KYC ( Front ) -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="kyc_document_front">KYC ( Front )</label>
                                                <input type="file" class="form-control p-1" id="kyc_document_front" name="kyc_document_front">
                                                @error('kyc_document_front') <div class="text-danger">{{ $message }}</div> @enderror

                                                @if(isset($record) && $record->kyc_document_front != '')
                                                <input type="hidden" name="old_kyc_document_front" value="{{ $record->kyc_document_front }}">
                                                <a href="{{ $record->kyc_document_front_url }}" alt="" class="mt-2" target="_blank">View</a>
                                                @endif
                                            </div>
                                        </div>
                                        <!-- End KYC ( Front ) -->

                                        <!-- KYC ( Back ) -->
                                        <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="kyc_document_back">KYC ( Back )</label>
                                                <input type="file" class="form-control p-1" id="kyc_document_back" name="kyc_document_back">
                                                @error('kyc_document_back') <div class="text-danger">{{ $message }}</div> @enderror

                                                @if(isset($record) && $record->kyc_document_back != '')
                                                <input type="hidden" name="old_kyc_document_back" value="{{ $record->kyc_document_back }}">
                                                <a href="{{ $record->kyc_document_back_url }}" alt="" class="mt-2" target="_blank">View</a>
                                                @endif
                                            </div>
                                        </div>
                                        <!-- End KYC ( Back ) -->

                                        <!-- KYC Status -->
                                        @php
                                        $status_border_view = '';

                                        if(($record->kyc_status ?? '') == 0):
                                            $status_border_view = 'border-warning';
                                        elseif(($record->kyc_status ?? '') == 1):
                                            $status_border_view = 'border-success';
                                        elseif(($record->kyc_status ?? '') == 2):
                                            $status_border_view = 'border-danger';
                                        endif;

                                        @endphp
                                        <div class="col-md-2">
                                            <div class="form-group">
                                                <label for="status">KYC Status</label>
                                                <select type="text" class="form-control {{ $status_border_view }}" id="kyc_status" name="kyc_status">
                                                    <option value="" disabled selected>Choose...</option>
                                                    <option value="0" {{ ( old('kyc_status', $record->kyc_status ?? '') == 0 ) ? 'selected' : '' }}>Pending</option>
                                                    <option value="1" {{ ( old('kyc_status', $record->kyc_status ?? '') == 1 ) ? 'selected' : '' }}>Approved</option>
                                                    <option value="2" {{ ( old('kyc_status', $record->kyc_status ?? '') == 2 ) ? 'selected' : '' }}>Rejected</option>
                                                </select>
                                            </div>
                                        </div>
                                        <!-- End KYC Status -->

                                        <!-- Details -->
                                         <div class="col-md-12 {{ $verify_aadhaar_number ? '' : 'd-none' }}">
                                            <table class="table table-bordered">
                                                <tr>
                                                    <th>Aadhar Card Number</th>
                                                    <td>{{ $verify_aadhaar_number }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Verify Aadhar Card DOB</th>
                                                    <td>{{ $verify_aadhaar_dob }}</td>
                                                </tr>
                                                <tr>
                                                    <th>Aadhar Address</th>
                                                    <td>{{ $aadhar_address }}</td>
                                                </tr>
                                            </table>
                                         </div>
                                        <!-- End Details -->

                                        <!-- End KYC -->

                                        <div class="col-md-12">
                                            <div class="text-right my-2">
                                                <button type="submit" class="btn bg-theme">Submit Profile</button>
                                            </div>
                                            <!-- /.card-body -->

                                        </div>


                                        <!-- Referred Users (loaded in UserController@edit with limits) -->
                                        @if(count($referral_users ?? []))
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Referred Users</h6>
                                                <span class="text-primary btn-hide-show" data-toggle='referred-users-wrapper'>Hide/ Show</span>
                                            </div>
                                            <hr style="border-top: 3px solid #8c8b8bc2;">

                                            <div class="row py-3" id="referred-users-wrapper" style="display: none;">
                                                <div class="col-md-12">
                                                    <div class="table-responsive">

                                                        <table class="table table-bordered m-0">
                                                            <thead>
                                                                <tr>
                                                                    <th>#</th>
                                                                    <th>Name</th>
                                                                    <th>Email</th>
                                                                    <th>Mobile</th>
                                                                    <th>Refer Amount ₹</th>
                                                                    <th>Income ₹</th>
                                                                    <th>Deposit ₹</th>
                                                                    <th>Total Balance ₹</th>
                                                                    <th>Date</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($referral_users ?? [] as $refer_user)
                                                                @php
                                                                    $dynamicClass = '';
                                                                    $totalReferCommissonAmount = (float) ($referralCommissionByUser[$refer_user->id] ?? 0);

                                                                    if ($refer_user->refer_by != $record->id) {
                                                                        $dynamicClass = 'text-line-through';
                                                                    }
                                                                @endphp
                                                                <tr>
                                                                    <td class="{{ $dynamicClass }}" >{{ $loop->iteration }}</td>
                                                                    <td class="{{ $dynamicClass }}">
                                                                        {{ $refer_user->name }}
                                                                        <small>( UID : <a href="{{ route('admin::users.edit', $refer_user->id); }}" target='_balnk'>{{ $refer_user->uid }}</a> )</small>
                                                                    </td>
                                                                    <td class="{{ $dynamicClass }}">{{$refer_user->email }}</td>
                                                                    <td class="{{ $dynamicClass }}">{{$refer_user->mobile }}</td>
                                                                    <td>
                                                                        <span >{{ $totalReferCommissonAmount }}</span>
                                                                    </td>
                                                                    <td class="{{ $dynamicClass }}">{{$refer_user->win_wallet_amount ?? 0 }}</td>
                                                                    <td class="{{ $dynamicClass }}">{{$refer_user->game_wallet_amount ?? 0 }}</td>
                                                                    <td class="{{ $dynamicClass }}">{{ ( $refer_user->win_wallet_amount ?? 0 ) + ($refer_user->game_wallet_amount ?? 0 ) }}</td>
                                                                    <td class="{{ $dynamicClass }}">{{ $refer_user->created_at }}</td>
                                                                    <td class="{{ $dynamicClass }}">{!! $refer_user->status_view !!}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- End Referred Users -->

                                        <!-- Completed Challenges -->
                                    
                                        @if(count($record->game_challenges ?? []))
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Game Challenges History</h6>
                                                <span class="text-primary btn-hide-show" data-toggle='completed-challenges-wrapper'>Hide/ Show</span>
                                            </div>
                                            <hr style="border-top: 3px solid #646464c2;">

                                            <div class="row py-3" id="completed-challenges-wrapper" style="display: none;">
                                                <div class="col-md-12">
                                                    <div class="table-responsive">

                                                        <table class="table table-bordered datatable">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 50px;" class="text-center">#</th>
                                                                    <th>Game</th>
                                                                    <th>Challenger</th>
                                                                    <th>Roomcode</th>
                                                                    <th>Opponent</th>
                                                                    <th>Amount</th>
                                                                    <th>Admin</th>
                                                                    <th>Paid</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($record->game_challenges ?? [] as $game_challenge)
                                                                <tr>
                                                                    <td>{{ $loop->iteration }}</td>
                                                                    <td>{!! $game_challenge->game_details !!}</td>

                                                                    <td>{!! $game_challenge->challenger_details !!}</td>
                                                                    <td>
                                                                        @php
                                                                        $roomcode_date_time = $game_challenge->roomcode_datetime ? "Time : " . date('Y-m-d h:i:m a', strtotime($game_challenge->roomcode_datetime)) : '';
                                                                        $roomcode_date_time = $game_challenge->roomcode_datetime ? "Time : " . $game_challenge->roomcode_datetime : '';
                                                                        @endphp
                                                                        {{ $game_challenge->roomcode }}
                                                                        <br>
                                                                        {{ $roomcode_date_time }}
                                                                    
                                                                    </td>


                                                                    <td>{!! $game_challenge->opponent_details !!}</td>
                                                                    <td>{{ $game_challenge->challenger_amount }}</td>
                                                                    <td>{{ $game_challenge->game_commission ? $game_challenge->game_commission_amount : 0 }}</td>
                                                                    <td>{{ $game_challenge->paid_amount }}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- End Completed Challenges -->

                                        <!-- Transaction History -->
                                        @if(count($wallet_history))
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Transaction History</h6>
                                                <span class="text-primary btn-hide-show" data-toggle='transaction-history-wrapper'>Hide/ Show</span>
                                            </div>
                                            <hr style="border-top: 3px solid #646464c2;">

                                            <div class="row py-3" id="transaction-history-wrapper" style="display: noned;">
                                                <div class="col-md-12">
                                                    <div class="table-responsive">

                                                        <table class="table table-bordered m-0 refer-commission-datatable">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 50px;" class="text-center">#</th>
                                                                    <th>Perticular</th>
                                                                    <th>Game Amount</th>
                                                                    <th>Win Amount</th>
                                                                    <th>Total Balance</th>
                                                                    <th>Date</th>
                                                                </tr>
                                                            </thead>

                                                            <tbody>
                                                                @foreach($wallet_history as $wallet_record)

                                                                <tr>
                                                                    <td>{{ $loop->iteration }}</td>
                                                                    <td>{{ $wallet_record->remark }}</td>
                                                                    <td>
                                                                        @if($wallet_record->wallet_type == 'game')
                                                                               @if($wallet_record->type == 'debit')
                                                                                  <span class="bg-danger p-2">- {{ $wallet_record->amount }}</span>
                                                                               @endif
                                                                               
                                                                               @if($wallet_record->type == 'credit')
                                                                               @php
                                                                                    $win_status     =  'bg-warning';
                                                                                    $win_status_icon     =  "🕒";
                        
                                                                                    if( $wallet_record->status == 1 ):
                                                                                        $win_status     =  'bg-success';
                                                                                        $win_status_icon     =  "✔️";
                                                                                    endif;
                                                                                    
                                                                                    if( $wallet_record->status == 2 ):
                                                                                        $win_status     =  'bg-danger';
                                                                                        $win_status_icon     =  "❌";
                                                                                    endif;
                                                                               @endphp
                                                                               <!-- $win_status_icon     =  "✔️"; -->
                                                                               <span class="{{ $win_status }} p-2">{!! $win_status_icon !!} {{ $wallet_record->amount }}</span>
                                                                            @endif
                                                                        @endif
                                                                     </td>
                                                                    <td>
                                                                        @if($wallet_record->wallet_type == 'win')
                                                                            @if($wallet_record->type == 'debit')
                                                                            @php
                                                                                    $win_status     =  'bg-warning';
                                                                                    $win_status_icon     =  "🕒";
                        
                                                                                    if( $wallet_record->status == 1 ):
                                                                                        $win_status     =  'bg-success';
                                                                                        $win_status_icon     =  "✔️";
                                                                                    endif;
                                                                                    
                                                                                    if( $wallet_record->status == 2 ):
                                                                                        $win_status     =  'bg-danger';
                                                                                        $win_status_icon     =  "❌";
                                                                                    endif;
                                                                               @endphp
                                                                              <span class="{{ $win_status }} p-2">{!! $win_status_icon !!} {{ $wallet_record->amount }}</span>
                                                                            @endif
                                                                               
                                                                            @if($wallet_record->type == 'credit')
                                                                            <span class="bg-success p-2">+ {{ $wallet_record->amount }}</span>
                                                                            @endif
                                                                        @endif
                                                                    </td>
                                                                    <td>
                                                                        @php
                                                                            $win_and_game_total_amount = $wallet_record->win_and_game_total_amount ?? 0;
                                                                            $win_and_game_total_amount = number_format($win_and_game_total_amount, 2);
                                                                            $win_and_game_total_amount = str_replace(',','',$win_and_game_total_amount);
                                                                        @endphp

                                                                        {{ $win_and_game_total_amount }}
                                                                    </td>
                                                                    <td>{{ $wallet_record->updated_at }}</td>
                                                                </tr>
                                                                @endforeach
                                                        </table>
                                                        </tbody>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- End Transaction History -->

                                        <!-- Deposit History -->
                                        @if(count($record->deposit ?? []))
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Deposit History</h6>
                                                <span class="text-primary btn-hide-show" data-toggle='deposit-history-wrapper'>Hide/ Show</span>
                                            </div>
                                            <hr style="border-top: 3px solid #646464c2;">

                                            <div class="row py-3" id="deposit-history-wrapper" style="display: none;">
                                                <div class="col-md-12">
                                                    <div class="table-responsive">

                                                        <table class="table table-bordered m-0 datatable">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 50px;" class="text-center">#</th>
                                                                    <th>TxnID</th>
                                                                    <th>Payment Info</th>
                                                                    <th>Amount</th>
                                                                    <th>TxnFee</th>
                                                                    <th>Final Amount</th>
                                                                    <th>Date</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>

                                                                @foreach($record->deposit ?? [] as $deposit)
                                                                <tr>
                                                                    <td>{{ $loop->iteration }}</td>
                                                                    <td>{{ $deposit->txn_id }}</td>
                                                                    <td>{{ $deposit->payment_info }}</td>
                                                                    <td>{{ $deposit->amount }}</td>
                                                                    <td>{{ $deposit->tax_fee }}</td>
                                                                    <td>{{ $deposit->final_amount }}</td>
                                                                    <td>{{ $deposit->created_at }}</td>
                                                                    <td>{!! $deposit->status_view !!}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        @endif
                                        <!-- End Deposit History -->

                                        <!-- withdrawal History -->
                                        @if(count($record->withdrawal ?? []))
                                        <div class="col-md-12 my-2">
                                            <div class="d-flex justify-content-between">
                                                <h6 class="m-0">Withdrawal History</h6>
                                                <span class="text-primary btn-hide-show" data-toggle='withdrawal-history-wrapper'>Hide/ Show</span>
                                            </div>
                                            <hr style="border-top: 3px solid #646464c2;">

                                            <div class="row py-3" id="withdrawal-history-wrapper" style="display: none;">
                                                <div class="col-md-12">
                                                    <div class="table-responsive">

                                                        <table class="table table-bordered m-0 datatable">
                                                            <thead>
                                                                <tr>
                                                                    <th style="width: 50px;" class="text-center">#</th>
                                                                    <th>TxnID</th>
                                                                    <th>Payment Info</th>
                                                                    <th>Amount</th>
                                                                    <th>TxnFee</th>
                                                                    <th>Final Amount</th>
                                                                    <th>Date</th>
                                                                    <th>Status</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach($record->withdrawal ?? [] as $withdrawal)
                                                                <tr>
                                                                    <td>{{ $loop->iteration }}</td>
                                                                    <td>{{ $withdrawal->txn_id }}</td>
                                                                    <td>{{ $withdrawal->payment_info }}</td>
                                                                    <td>{{ $withdrawal->amount }}</td>
                                                                    <td>{{ $withdrawal->tax_fee }}</td>
                                                                    <td>{{ $withdrawal->final_amount }}</td>
                                                                    <td>{{ $withdrawal->created_at }}</td>
                                                                    <td>{!! $withdrawal->status_view !!}</td>
                                                                </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    @endif
                                    <!-- End withdrawal History -->
                            </div>
                        </div>


                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('script')
<script>
    $(document).on('click', '.btn-hide-show', function() {
        content_wrapper = $(this).data('toggle')
        console.log(content_wrapper)
        $('#' + content_wrapper).slideToggle()
    })

    $('.datatable').dataTable()
    $('.refer-commission-datatable').dataTable({
        order: [
            [0, 'asc']
        ]
    })
</script>
@endsection
