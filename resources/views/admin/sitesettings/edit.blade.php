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
                    
                    <div class="card-header bg-theme">
                    
                        <div class="row">
                            <div class="col-sm-6">
                                <h5 class="m-0">Site Settings</h5>
                            </div><!-- /.col -->
                        </div><!-- /.row -->
                    </div>
                    <!-- form start -->
                    <form method="post" action="{{ route('admin::sitesettings.update',$sitesetting->id) }}" enctype="multipart/form-data">
                    
                        @csrf
                        @method('PUT')
                        <div class="card-body px-5">
                        @if(session()->has('back_msg'))
                        {!! session()->get('back_msg') !!}
                    @endif
                            <div class="row">
                                <!-- Basic Details -->
                                <div class="col-md-12">
                                    <details open class="my-2" style="padding: 12px; ">
                                        <summary>Basic Details</summary>
                                        <div class="row p-4">

                                            <!-- Site Name -->
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="name">Site Name</label>
                                                    <input type="text" class="form-control" id="site_name" name="site_name" placeholder="Enter site name" value="{{ old('site_name', $sitesetting->site_name) }}">
                                                </div>
                                            </div>
                                            <!-- End Site Name -->

                                            <div class="form-group col-md-4">
                                                <label for="logo">Logo</label>
                                                <input type="file" class="form-control" name="site_logo" id="logo" accept="image/*">
                                                @if (isset($sitesetting->logo) && $sitesetting->logo != '')
                                                <a target='_blank' href="{{ asset('storage/site').'/'.$sitesetting->logo }}" class='text-primary'>View</a>
                                                <input type="hidden" class="form-control" name="old_site_logo" value="{{ $sitesetting->logo }}">
                                                @endif
                                            </div>

                                            <div class="form-group col-md-4">
                                                <label for="fav-icon">Fav-icon</label>
                                                <input type="file" class="form-control" name="site_fav_icon" id="fav-icon" accept="image/*">
                                                @if (isset($sitesetting->fav_icon) && $sitesetting->fav_icon != '')
                                                <a target='_blank' href="{{ asset('storage/site').'/'.$sitesetting->fav_icon }}" class='text-primary'>View</a>
                                                <input type="hidden" class="form-control" name="old_site_fav_icon" value="{{ $sitesetting->fav_icon }}">
                                                @endif
                                            </div>

                                            <!-- Bg Theme Color -->
                                            <div class="col-md-4 align-self-center">
                                                <div class="form-group">
                                                    <label for="" class="pr-2">Background Color 1</label>
                                                    <input type="color" class="" name="theme[bg_color_one]" value="{{ site_setting()->theme->bg_color_one ?? '#ffffff' }}">
                                                </div>
                                            </div>

                                            <div class="col-md-4 align-self-center">
                                                <div class="form-group">
                                                    <label for="" class="pr-2">Background Color 2</label>
                                                    <input type="color" class="" name="theme[bg_color_two]" value="{{ site_setting()->theme->bg_color_two ?? '#ffffff' }}">
                                                </div>
                                            </div>

                                            <div class="col-md-4 align-self-center">
                                                <div class="form-group">
                                                    <label for="" class="pr-2">Background Text Color</label>
                                                    <input type="color" class="" name="theme[bg_text_color_one]" value="{{ site_setting()->theme->bg_text_color_one ?? '#ffffff' }}">
                                                </div>
                                            </div>

                                            <!-- Bg Theme Color -->

                                            <!-- Mobile -->
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="mobile">Mobile</label>
                                                    <input type="text" class="form-control" id="mobile" name="mobile" placeholder="Enter mobile" value="{{ old('mobile', $sitesetting->mobile) }}">
                                                </div>
                                            </div>
                                            <!-- End Mobile -->

                                            <!-- Email -->
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="">Email</label>
                                                    <input type="text" class="form-control" name="email" placeholder="Enter email" value="{{ old('email', $sitesetting->email) }}">
                                                </div>
                                            </div>
                                            <!-- End Email -->

                                            <!-- Theme -->
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="theme_type">Theme Type</label>
                                                    <select type="text" class="form-control" id="theme_type" name="theme_type">
                                                        <option value="" disabled selected>Choose...</option>
                                                        <option value="light_mode" {{ ( old('theme_type', $sitesetting->theme_type ?? '') == 'light_mode' ) ? 'selected' : '' }}>Light</option>
                                                        <option value="dark_mode" {{ ( old('theme_type', $sitesetting->theme_type ?? '') == 'dark_mode' ) ? 'selected' : '' }}>Dark</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End Theme -->

                                        </div>
                                    </details>
                                </div>
                                <!-- End Basic Details -->

                                <!-- App Setting -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px;" open>
                                        <summary>App Setting</summary>
                                        <div class="row p-4">
                                            <!-- App Version -->
                                            <div class="col-md-3">
                                                <label for="">App Version</label>
                                                <input type="text" class="form-control" name="app_details[app_version]" placeholder="Enter app version" value="{{ $sitesetting->app_details->app_version ?? '' }}">
                                            </div>
                                            <!-- End App Version -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="force_update">Force Update</label>
                                                    <select type="text" class="form-control" name="app_details[is_force_update]">
                                                        <option value="1" @selected(($sitesetting->app_details->is_force_update ?? 0) == 1)>Yes</option>
                                                        <option value="0" @selected(($sitesetting->app_details->is_force_update ?? 0) == 0)>No</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End App Version -->

                                            <!-- APK File -->
                                             <div class="col-md-3">
                                            <div class="form-group">
                                                <label for="apk_file">APK File</label>
                                                <input type="file" class="form-control" name="site_apk_file" id="apk_file" accept=".apk">
                                                <input type="hidden" class="form-control" name="old_site_apk_file" value="{{ $sitesetting->apk_file ?? '' }}">
                                                @if ($sitesetting->apk_file ?? 0)
                                                <a target='_blank' href="{{ $sitesetting->apk_file_url }}" class='text-primary'>Download</a>
                                                @endif
                                            </div>
                                            </div>
                                            
                                            <!-- End APK File -->
                                        </div>
                                    </details>
                                </div>

                                <!-- Payment Gateway -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px;" open>
                                        <summary>Payment Gateway</summary>
                                        <div class="row p-4">
                                            <!-- Payment Gateway -->
                                            <div class="col-md-3">
                                                <label for="">Payment Gateway</label>
                                                <select name="payment_gateway" id="payment_gateway" class="form-control">
                                                    <option value="" disabled selected>Choose...</option>
                                                    <option value="manually" {{ ( $sitesetting->payment_gateway ?? '' ) == 'manually' ? 'selected' : '' }}>Manually</option>
                                                    <option value="cashfree" {{ ( $sitesetting->payment_gateway ?? '' ) == 'cashfree' ? 'selected' : '' }}>Cashfree</option>
                                                    <option value="rozarpay" {{ ( $sitesetting->payment_gateway ?? '' ) == 'rozarpay' ? 'selected' : '' }}>Rozarpay</option>
                                                    <option value="upigateway" {{ ( $sitesetting->payment_gateway ?? '' ) == 'upigateway' ? 'selected' : '' }}>UPI Gateway (ekqr)</option>
                                                </select>
                                            </div>
                                            <!-- End Payment Gateway -->
                                        </div>

                                        <div class="row px-4">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="">Cashfree API KEY</label>
                                                    <input type="text" class="form-control" name="cashfree_api_key" placeholder="Enter cashfree api key" value="{{ $sitesetting->cashfree_api_key ?? '' }}">
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="">Cashfree API SECRET</label>
                                                    <input type="text" class="form-control" name="cashfree_api_secret" placeholder="Enter cashfree api secret" value="{{ $sitesetting->cashfree_api_secret ?? '' }}">
                                                </div>
                                            </div>

                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="">Rozarpay API KEY</label>
                                                    <input type="text" class="form-control" name="rozarpay_api_key" placeholder="Enter rozarpay api key" value="{{ $sitesetting->rozarpay_api_key ?? '' }}">
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label for="">Rozarpay API SECRET</label>
                                                    <input type="text" class="form-control" name="rozarpay_api_secret" placeholder="Enter rozarpay api secret" value="{{ $sitesetting->rozarpay_api_secret ?? '' }}">
                                                </div>
                                            </div>

                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="">UPI Gateway API KEY</label>
                                                    <input type="text" class="form-control" name="upigateway_api_key" placeholder="Enter UPI Gateway (ekqr) api key" value="{{ $sitesetting->upigateway_api_key ?? '' }}">
                                                </div>
                                            </div>
                                            
                                        </div>
                                    </details>
                                </div>

                                <!-- OTP / SMS Gateway -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px;" open>
                                        <summary>OTP / SMS Gateway</summary>
                                        <div class="row p-4">
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label for="sms_otp_provider">OTP Provider</label>
                                                    <select name="sms_otp_provider" id="sms_otp_provider" class="form-control">
                                                        <option value="fast2sms" {{ ( $sitesetting->sms_otp_provider ?? 'fast2sms' ) == 'fast2sms' ? 'selected' : '' }}>1 — Fast2SMS (existing)</option>
                                                        <option value="vb_http" {{ ( $sitesetting->sms_otp_provider ?? '' ) == 'vb_http' ? 'selected' : '' }}>2 — VB HTTP GET (custom URL below)</option>
                                                    </select>
                                                    <small class="text-muted d-block mt-1">Option 1 uses your Fast2SMS env key. Option 2 calls the URL template on each OTP send.</small>
                                                </div>
                                            </div>

                                            <div class="col-md-8">
                                                <div class="form-group">
                                                    <label for="sms_vb_api_url_template">VB HTTP URL template (option 2 only)</label>
                                                    <input type="text" class="form-control" name="sms_vb_api_url_template" id="sms_vb_api_url_template"
                                                        placeholder="https://78.46.58.54/vb/apikey.php?apikey=...&number=8104057472&message=Your%20OTP%20IS%20{var}%20..."
                                                        value="{{ $sitesetting->sms_vb_api_url_template ?? '' }}">
                                                    <small class="text-muted">Only two changes at send time: the whole value after <code>number=</code> (until <code>&amp;</code>) becomes the requesting mobile (10 digits). Only <code>{var}</code> is substituted — with the OTP inside <code>message=…</code>, e.g. <code>Your%20OTP%20IS%20{var}%20.</code> Everything else including every <code>%20</code> stays exactly as saved. HTTPS-to-IP uses <code>withoutVerifying()</code> unless <code>SMS_VB_HTTP_VERIFY_SSL=true</code> in <code>.env</code>.</small>
                                                </div>
                                            </div>
                                        </div>
                                    </details>
                                </div>
                                <!-- End OTP / SMS Gateway -->

                                <!-- Game Setting -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px;" open>
                                        <summary>Game Setting</summary>

                                        <div class="row p-4">

                                            <!-- Deposit Scanner -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="deposit-scanner-img">Deposit Scanner Image</label>
                                                    <input type="file" class="form-control" name="site_deposit_scanner_img" id="deposit-scanner-img" accept="image/*">
                                                    @if (isset($sitesetting->deposit_scanner_img) && $sitesetting->deposit_scanner_img != '')
                                                    <a target='_blank' href="{{ $sitesetting->deposit_scanner_img_url }}" class='text-primary'>View</a>
                                                    <input type="hidden" class="form-control" name="old_site_deposit_scanner_img" value="{{ $sitesetting->deposit_scanner_img }}">
                                                    @endif
                                                </div>
                                            </div>
                                            <!-- End Deposit Scanner -->

                                            <!-- UPI ID -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="upi_id">UPI ID</label>
                                                    <input type="text" class="form-control" name="upi_id" placeholder="Enter UPI ID" value="{{ old('upi_id', $sitesetting->upi_id) }}">
                                                    <!--<small class="text-muted">( Game play issue/ Deposit issue )</small>-->
                                                </div>
                                            </div>
                                            <!-- End UPI ID -->

                                            <!-- Whatsapp Number -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="whatsapp_support">Whatsapp support</label>
                                                    <input type="text" class="form-control" name="whatsapp_support" placeholder="Enter whatsapp support" value="{{ old('whatsapp_support', $sitesetting->whatsapp_support) }}">
                                                    <!--<small class="text-muted">( Game play issue/ Deposit issue )</small>-->
                                                </div>
                                            </div>
                                            <!-- End Whatsapp Number -->
                                            
                                            <!-- Deposit Issue Number -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="whatsapp_support">Deposit Issue Mobile</label>
                                                    <input type="text" class="form-control" name="deposit_issue_support_mobile" placeholder="Enter Deposit Issue Mobile" value="{{ old('deposit_issue_support_mobile', $sitesetting->deposit_issue_support_mobile) }}">
                                                    <!--<small class="text-muted">( Game play issue/ Deposit issue )</small>-->
                                                </div>
                                            </div>
                                            <!-- End Deposit Issue Number -->
                                            
                                            <!-- Game Play Issue Number -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="whatsapp_support">Game Play Issue Mobile</label>
                                                    <input type="text" class="form-control" name="game_play_issue_support_mobile" placeholder="Enter Game Play Issue Mobile" value="{{ old('game_play_issue_support_mobile', $sitesetting->game_play_issue_support_mobile) }}">
                                                    <!--<small class="text-muted">( Game play issue/ Game Play issue )</small>-->
                                                </div>
                                            </div>
                                            <!-- End Game Play Issue Number -->

                                            <!-- Telegram Number -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Telegram support</label>
                                                    <input type="text" class="form-control" name="telegram_support" placeholder="Enter telegram support" value="{{ old('telegram_support', $sitesetting->telegram_support) }}">
                                                </div>
                                            </div>
                                            <!-- End Telegram Number -->

                                            <!-- YouTube Help Video -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">YouTube Help Video</label>
                                                    <input type="text" class="form-control" name="youtube_help_video" placeholder="Enter YouTube Help Video" value="{{ old('youtube_help_video', $sitesetting->youtube_help_video) }}">
                                                </div>
                                            </div>
                                            <!-- End YouTube Help Video -->

                                            <!-- Penalty amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Penalty amount</label>
                                                    <input type="text" class="form-control" name="penalty_amount" placeholder="Enter penalty amount"  value="{{ old('penalty_amount', $sitesetting->penalty_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Penalty amount -->

                                            <!-- Minimum Game play amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Minimum Game play amount</label>
                                                    <input type="text" class="form-control" name="minimum_game_play_amount" placeholder="Enter Minimum Game play amount" value="{{ old('minimum_game_play_amount', $sitesetting->minimum_game_play_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Minimum Game play amount -->

                                            <!-- Maximum Game play amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Maximum Game play amount</label>
                                                    <input type="text" class="form-control" name="maximum_game_play_amount" placeholder="Enter maximum Game play amount" value="{{ old('maximum_game_play_amount', $sitesetting->maximum_game_play_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Maximum Game play amount -->

                                            <!-- Minimum deposit amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Minimum deposit amount</label>
                                                    <input type="text" class="form-control" name="minimum_deposit_amount" placeholder="Enter minimum deposit amount" value="{{ old('minimum_deposit_amount', $sitesetting->minimum_deposit_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Minimum deposit amount -->

                                            <!-- Maximum deposit amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Maximum deposit amount</label>
                                                    <input type="text" class="form-control" name="maximum_deposit_amount" placeholder="Enter maximum deposit amount"  value="{{ old('maximum_deposit_amount', $sitesetting->maximum_deposit_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Maximum deposit amount -->

                                            <!-- Minimum withdrawal limit -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Minimum withdrawal limit</label>
                                                    <input type="text" class="form-control" name="minimum_withdrawal_limit" placeholder="Enter minimum withdrawal limit" value="{{ old('minimum_withdrawal_limit', $sitesetting->minimum_withdrawal_limit) }}">
                                                </div>
                                            </div>
                                            <!-- End Minimum withdrawal limit -->

                                            <!-- Maximum withdrawal limit -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Maximum withdrawal limit</label>
                                                    <input type="text" class="form-control" name="maximum_withdrawal_limit" placeholder="Enter maximum withdrawal limit" value="{{ old('maximum_withdrawal_limit', $sitesetting->maximum_withdrawal_limit) }}">
                                                </div>
                                            </div>
                                            <!-- End Maximum withdrawal limit -->

                                            <!-- Without kyc withdrawal limit -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Without kyc withdrawal limit</label>
                                                    <input type="text" class="form-control" name="without_kyc_withdrawal_limit" placeholder="Enter without kyc withdrawal limit" value="{{ old('without_kyc_withdrawal_limit', $sitesetting->without_kyc_withdrawal_limit) }}">
                                                </div>
                                            </div>
                                            <!-- End Without kyc withdrawal limit -->

                                            <!-- Ulta Ludo -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="ulta_ludo">Ulta Ludo</label>
                                                    <select type="text" class="form-control {{ (($sitesetting->ulta_ludo_status ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="ulta_ludo" name="ulta_ludo_status">
                                                        <option value="" disabled selected>Choose...</option>
                                                        <option value="1" {{ ( old('ulta_ludo_status', $sitesetting->ulta_ludo_status ?? '') == 1 ) ? 'selected' : '' }}>Active</option>
                                                        <option value="0" {{ ( old('ulta_ludo_status', $sitesetting->ulta_ludo_status ?? '') == 0 ) ? 'selected' : '' }}>Deactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End Ulta Ludo -->
                                             
                                            <!-- All withdrawal -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="all_withdrawal">All withdrawal</label>
                                                    <select type="text" class="form-control {{ (($sitesetting->all_withdrawal_status ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="all_withdrawal" name="all_withdrawal_status">
                                                        <option value="" disabled selected>Choose...</option>
                                                        <option value="1" {{ ( old('all_withdrawal_status', $sitesetting->all_withdrawal_status ?? '') == 1 ) ? 'selected' : '' }}>Active</option>
                                                        <option value="0" {{ ( old('all_withdrawal_status', $sitesetting->all_withdrawal_status ?? '') == 0 ) ? 'selected' : '' }}>Deactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End All withdrawal -->

                                            <!-- Refer -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="all_refer">All Refer</label>
                                                    <select type="text" class="form-control {{ (($sitesetting->all_refer_status ?? '') == 1) ? 'border-success' : 'border-danger' }}" id="all_refer" name="all_refer_status">
                                                        <option value="" disabled selected>Choose...</option>
                                                        <option value="1" {{ ( old('all_refer_status', $sitesetting->all_refer_status ?? '') == 1 ) ? 'selected' : '' }}>Active</option>
                                                        <option value="0" {{ ( old('all_refer_status', $sitesetting->all_refer_status ?? '') == 0 ) ? 'selected' : '' }}>Deactive</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End Refer -->

                                            <!-- Withdrawal Status -->
                                            <div class="col-2">
                                                <div class="form-group">
                                                    <label for="name">Withdrawal Status</label>
                                                    <br>
                                                    {!! $sitesetting->all_withdrawal_status_view !!}
                                                </div>
                                            </div>
                                            <!-- End Withdrawal Status -->

                                            <!-- All Refer Status -->
                                            <div class="col-2">
                                                <div class="form-group">
                                                    <label for="name">All Refer Status</label>
                                                    <br>
                                                    {!! $sitesetting->all_refer_status_view !!}
                                                </div>
                                            </div>
                                            <!-- End Refer Status -->

                                            <!-- Refer to -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="refer_to">Refer to</label>
                                                    <select type="text" class="form-control" id="refer_to" name="refer_to">
                                                        <option value="" disabled selected>Choose...</option>
                                                        <option value="win_amount" {{ ( old('refer_to', $sitesetting->refer_to ?? '') == 'win_amount' ) ? 'selected' : '' }}>Win Amount</option>
                                                        <option value="game_amount" {{ ( old('refer_to', $sitesetting->refer_to ?? '') == 'game_amount' ) ? 'selected' : '' }}>Game Amount</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <!-- End Refer to -->

                                            <!-- Win to Game Cashback Percentage -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Win to Game Cashback ( % )</label>
                                                    <input type="text" class="form-control" name="win_to_game_cashback_percentage" placeholder="Enter Win to Game Cashback (%)" value="{{ old('win_to_game_cashback_percentage', $sitesetting->win_to_game_cashback_percentage) }}">
                                                </div>
                                            </div>
                                            <!-- End Win to Game Cashback Percentage -->

                                            <!-- Minimum Win Amount -->
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label for="">Minimum Win Amount</label>
                                                    <input type="text" class="form-control" name="minimum_win_amount" placeholder="Enter Minimum Win Amount" value="{{ old('minimum_win_amount', $sitesetting->minimum_win_amount) }}">
                                                </div>
                                            </div>
                                            <!-- End Minimum Win Amount -->
                                            
                                            <!-- Privacy Policy -->
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="">Privacy Policy</label>
                                                    <textarea class="form-control ckeditor" name="privacy_policy" placeholder="Enter privacy policy">{{ old('privacy_policy', $sitesetting->privacy_policy) }}</textarea>
                                                </div>
                                            </div>
                                            <!-- End Privacy Policy -->

                                            <!-- Rules -->
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="">Rules</label>
                                                    <textarea class="form-control ckeditor" name="rules" placeholder="Enter rules">{{ old('rules', $sitesetting->rules) }}</textarea>
                                                </div>
                                            </div>
                                            <!-- End Rules -->

                                            <!-- Important Notification -->
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="">Important Notification</label>
                                                    <textarea class="form-control ckeditor" name="important_notification" placeholder="Enter important notification">{{ old('important_notification', $sitesetting->important_notification) }}</textarea>
                                                </div>
                                            </div>
                                            <!-- End Rules -->
                                        </div>
                                    </details>
                                </div>
                                <!-- Game Setting -->

                                <!-- Address -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px; ">
                                        <summary>Address details</summary>

                                        <div class="row p-4">
                                            <div class="form-group col-md-6">
                                                <label for="streetAddress">Street Address</label>
                                                <input type="text" class="form-control" id="streetAddress" name="address[streetAddress]" placeholder="Enter Street Address" value="{{ old('address[streetAddress]', $sitesetting->address->streetAddress) }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="addressLocality">Address Locality</label>
                                                <input type="text" class="form-control" id="addressLocality" name="address[addressLocality]" placeholder="Enter Address Locality" value="{{ old('address[streetAddress]', $sitesetting->address->addressLocality) }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="addressRegion">Address Region</label>
                                                <input type="text" class="form-control" id="addressRegion" name="address[addressRegion]" placeholder="Enter Address Region" value="{{ old('address[streetAddress]', $sitesetting->address->addressRegion) }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="postalCode">Postal Code</label>
                                                <input type="text" class="form-control" id="postalCode" name="address[postalCode]" placeholder="Enter Postal Code" value="{{ old('address[streetAddress]', $sitesetting->address->postalCode) }}">
                                            </div>


                                        </div>
                                    </details>
                                </div>
                                <!-- End Address -->


                                <!-- Social Links -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px; ">
                                        <summary>Social Links</summary>
                                        <div class="row p-4">
                                            <div class="form-group col-md-6">
                                                <label for="facebook">Facebook</label>
                                                <input type="url" class="form-control" id="facebook" name="socialLinks[facebook]" placeholder="Enter facebook URL" value="{{ old('socialLinks[facebook]', $sitesetting->socialLinks->facebook ?? '') }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="twitter">Twitter</label>
                                                <input type="url" class="form-control" id="twitter" name="socialLinks[twitter]" placeholder="Enter twitter URL" value="{{ old('socialLinks[twitter]', $sitesetting->socialLinks->twitter ?? '') }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="youtube">Youtube</label>
                                                <input type="url" class="form-control" id="youtube" name="socialLinks[youtube]" placeholder="Enter youtube URL" value="{{ old('socialLinks[youtube]', $sitesetting->socialLinks->youtube ?? '') }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="instagram">Instagram</label>
                                                <input type="url" class="form-control" id="instagram" name="socialLinks[instagram]" placeholder="Enter instagram URL" value="{{ old('socialLinks[instagram]', $sitesetting->socialLinks->instagram ?? '') }}">
                                            </div>

                                            <div class="form-group col-md-6">
                                                <label for="linkedin">Linkedin</label>
                                                <input type="url" class="form-control" id="linkedin" name="socialLinks[linkedin]" placeholder="Enter linkedin URL" value="{{ old('socialLinks[linkedin]', $sitesetting->socialLinks->linkedin ?? '') }}">
                                            </div>
                                        </div>
                                    </details>
                                </div>
                                <!-- Social Links -->

                                <!-- Additonal Details -->
                                <div class="col-md-12">
                                    <details class="my-2" style="padding: 12px; ">
                                        <summary>Additonal details</summary>

                                        <div class="row p-4">
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="header_line">Header Line</label>
                                                    <input type="text" class="form-control" id="header_line" name="header_line" placeholder="Enter header line" value="{{ old('header_line', $sitesetting->header_line) }}">
                                                </div>
                                            </div>


                                            <div class="form-group col-md-12">
                                                <label for="top_header_text">Top Header Text</label>
                                                <textarea class="form-control" id="top_header_text" name="top_header_text" placeholder="Enter top header text">{{ old('top_header_text', $sitesetting->top_header_text) }}</textarea>
                                            </div>

                                            <div class="form-group col-md-12">
                                                <label for="footer_text">Footer Text</label>
                                                <textarea class="form-control" id="footer_text" name="footer_text" placeholder="Enter footer text">{{ old('footer_text', $sitesetting->footer_text) }}</textarea>
                                            </div>

                                            <div class="form-group col-md-12">
                                                <label for="disclaimer">Disclaimer</label>
                                                <textarea class="form-control" id="disclaimer" name="disclaimer" rows="10" placeholder="Enter disclaimer here">{{ old('disclaimer', $sitesetting->disclaimer) }}</textarea>
                                            </div>

                                            <div class="form-group col-md-12">
                                                <label for="custom_css">Custom CSS</label>
                                                <textarea class="form-control" id="custom_css" name="custom_css" rows="10" placeholder="Enter Custom CSS here">{{ old('footer_text', $sitesetting->custom_css) }}</textarea>
                                            </div>

                                            <div class="form-group col-md-12">
                                                <label for="custom_css">Custom JS</label>
                                                <textarea class="form-control" id="custom_js" name="custom_js" rows="10" placeholder="Enter Custom JS here">{{ old('footer_text', $sitesetting->custom_js) }}</textarea>
                                            </div>

                                        </div>
                                    </details>
                                </div>
                                <!-- End Additonal Details -->

                            </div>
                            <!-- /.card-body -->

                            <div class="text-center">

                                <button type="submit" class="btn bg-theme my-2">Update</button>
                            </div>
                    </form>
                </div>
                <!-- /.card -->
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>
<!-- /.content -->

@endsection

@section('content_js')
<script>
    //Date picker
    $('#reservationdate').datetimepicker({
        format: 'L'
    });
</script>
@endsection
