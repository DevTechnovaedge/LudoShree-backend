<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin</title>

  <!-- Google Font: Source Sans Pro -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/fontawesome-free/css/all.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
  <!-- Select2 -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/select2/css/select2.min.css">
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
  <!-- Tempusdominus Bootstrap 4 -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
  <!-- JQVMap -->
  <!-- <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/jqvmap/jqvmap.min.css"> -->

  <!-- DataTables -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css">
  <!-- <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/datatables-responsive/css/responsive.bootstrap4.min.css"> -->
  <!-- <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/datatables-buttons/css/buttons.bootstrap4.min.css"> -->

  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.1/css/jquery.dataTables.min.css">

  <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.3.3/css/buttons.dataTables.min.css">

  <!-- Theme style -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/dist/css/adminlte.min.css">
  <!-- overlayScrollbars -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
  <!-- Daterange picker -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/daterangepicker/daterangepicker.css">
  <!-- summernote -->
  <link rel="stylesheet" href="{{ asset('admin-assets') }}/plugins/summernote/summernote-bs4.min.css">

  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.0/themes/base/jquery-ui.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="{{ asset('admin-assets/dist/css/style.css') }}">

  
  <style>
    body {
      overflow-x: hidden;
    }

    .error {
      color: red;
    }

    .cke_notifications_area {
      display: none;
    }

    .select2-selection__rendered,
    .select2-results__option {
      font-size: 12px !important;
    }


    .select2-container--default .select2-selection--single .select2-selection__arrow {
      top: 7px;
    }

    @font-face {
      font-family: "Poppins-Regular";
      src:url("{{ asset('admin-assets/font/Poppins/Poppins-Regular.ttf') }}")
    }

    aside a.nav-link {
      padding: 10px;
    }

    /* Dynamic Css Theme */
    .bg-theme {
      background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>);
      color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;
    }

    .bg-theme:hover {
      background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>);
      color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;
    }

    aside a.nav-link.active {
      background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>) !important;
    }

    aside a.nav-link:hover {
      background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>) !important;
    }

    aside li.nav-item.menu-is-opening.menu-open {
      background: linear-gradient(45deg, <?= site_setting()->theme->bg_color_one ?? '#6c63ff'; ?>, <?= site_setting()->theme->bg_color_two ?? '#6c63ff99'; ?>);
      color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?>;
    }

    aside li.nav-item.menu-is-opening.menu-open>a {
      color: <?= site_setting()->theme->bg_text_color_one ?? '#fff' ?> !important;
    }

    aside ul.nav.nav-treeview a:hover {
      background: <?= (site_setting()->theme->bg_color_one ?? '') . '30' ?? '#6c63ff30'; ?> !important;
    }

    aside li.nav-item.active {
      background: <?= (site_setting()->theme->bg_color_one ?? '') . '30' ?? '#6c63ff30'; ?> !important;
    }

    details {
      background: <?= (site_setting()->theme->bg_color_one ?? '') . '08' ?? '#6c63ff12'; ?> !important;
    }

    /* End Dynamic Css Theme */

    .nav-sidebar .nav-link>.right,
    .nav-sidebar .nav-link>p>.right {
      top: 1rem;
    }

    /* End Theme */

    a.nav-link p {
      font-size: 12px !important;
    }

    /* Gradiant */
    .yellow-gradiant {
      background: linear-gradient(74deg, #fdeb71, #f8d800);
    }

    .blue-gradiant {
      background: linear-gradient(74deg, #abdcff, #0396ff);
    }

    .red-gradiant {
      background: linear-gradient(74deg, #feb692, #ea5455);
    }

    .voilet-gradiant {
      background: linear-gradient(74deg, #ce9ffc, #7367f0);
    }

    .green-gradiant {
      background: linear-gradient(74deg, #81fbb8, #28c76f);
    }

    /* End Gradiant */

    /* Dark and Normal */
    .bg-green-dark {
      background: green !important;
    }

    .bg-green {
      background: #28a745 !important;
    }

    .bg-orange {
      background: #fd7e14 !important;
    }

    .bg-orange-dark {
      background: #da6300 !important;
    }

    .bg-pink {
      background: #FF1493 !important;
    }

    .bg-pink-dark {
      background: #C71585 !important;
      color: #fff;
    }

    .bg-violet {
      background: #8A2BE2 !important;
    }

    .bg-violet-dark {
      background: #680193 !important;
    }

    .bg-violet {
      background: #00BFFF !important;
    }

    .bg-violet-dark {
      background: #1E90FF !important;
    }

    .bg-brown {
      background: #be7948 !important;
    }

    .bg-brown-dark {
      background: #8B4513 !important;
    }

    .bg-silver {
      background: #C0C0C0 !important;
    }

    .bg-silver-dark {
      background: #A9A9A9 !important;
    }

    .bg-purple {
      background: #A963EA !important;
    }

    .bg-purple-dark {
      background: #8A2BE2 !important;
    }

    .bg-red {
      background: red !important;
    }

    .bg-red-dark {
      background: #bb0000 !important;
    }

    /* End Dark and Normal */

    td,
    th {
      text-wrap: nowrap;
    }

    table {
      width: 100% !important;
    }

    label,
    input,
    select,
    .btn,
    table td,
    table td div,
    table td span,
    table td small,
    table td small a,
    table th,
    table tr,
    table small,
    table small a {
      font-size: 12px !important;
    }

    label {
      font-weight: 100 !important;
    }

    table .btn {
      font-size: 10px !important;
    }

    .header-icon {
      font-size: 1.7rem !important;
    }

    /* @if(( $sitesetting_details->theme_type ?? '' ) != 'dark_mode')
    aside.main-sidebar {
      background: #fff;
    }

    @else
    aside.main-sidebar {
      background: #3e454c;
    box-shadow: 1px 1px 1px gray;
}
    @endif */

    .main-header .nav-link {
      height: auto;
    }

    small {
      font-size: 11px !important;
    }

    .header-icon-anchor,
    .header-icon-anchor:hover {
      cursor: pointer;
      color: #343a40;
    }
    
    @media(max-width: 992px){
        .sidebar { background: #fff; }
    }
  </style>

  @yield("style")
  @yield("component-style")
</head>

<body class="hold-transition sidebar-mini layout-fixed {{ ( $sitesetting_details->theme_type ?? '' ) == 'dark_mode' ? 'dark-mode' : '' }}">
  <div class="wrapper">

    <!-- Preloader -->
    <!-- <div class="preloader flex-column justify-content-center align-items-center">
      <img class="animation__shake" src="{{ asset('admin-assets') }}/dist/img/AdminLTELogo.png" alt="AdminLTELogo" height="60" width="60">
    </div> -->

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
      <!-- Left navbar links -->
      <ul class="navbar-nav col-md-1">
        <li class="nav-item">
          <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
        </li>

      </ul>
      <ul class="navbar-nav col-md-7">
        @php
        $display = 'd-none';
        if(request()->is('admin/users') || request()->is('admin/game-challenges')
        || request()->is('admin/game-credit-and-debit') || request()->is('admin/win-credit-and-debit')
        || request()->is('admin/transactions') || request()->is('admin/game-ledger') || request()->is('admin/win-to-game-cashbacks')
        ):
        $display = '';
        endif;

        @endphp

        <li class="nav-item w-100 {{ $display }} ">
          <input type="search" class="form-control" placeholder="Serach..." id="serach-datatable">
        </li>
      </ul>

      <!-- Header -->
       <div class="col-md-4">
      <div class="row">
        
        <!-- Optimize Space -->
        <div class="col-md-3 text-center align-self-center">
          <a class="nav-link header-icon-anchor" href="{{ url('admin/optimize-space') }}" onclick="return confirm('Are you sure to delete old files?')">
            <i class="fa fa-bolt header-icon"></i>
            <label for="" class="m-0 header-icon-anchor">Optimize</label>
          </a>
        </div>
        <!-- End Optimize Space -->
        
        <!-- Setting -->
        <div class="col-md-3 text-center align-self-center">
          <a class="nav-link header-icon-anchor" href="{{ url('admin/sitesettings') }}">
            <i class="fa fa-cog header-icon"></i>
            <label for="" class="m-0 header-icon-anchor">Setting</label>
          </a>
        </div>
        <!-- End Setting -->

        <!-- Change Password -->
        <div class="col-md-3 text-center align-self-center">
          <span class="nav-link header-icon-anchor change-password-btn">
            <i class="fa fa-user-shield header-icon"></i>
            <label for="" class="m-0 header-icon-anchor">Password</label>
          </span>
        </div>
        <!-- End Change Password -->


        <!-- Logout -->
        <div class="col-md-3 text-center align-self-center">
        <form id="logout-form" action="{{ url('admin/logout') }}" method="post">
            @csrf
            <button type="button" onclick="confirmLogout()" class="bg-transparent border-0 d-block nav-link text-dark" title="Logout">
              <i class="fas  fa-power-off header-icon"></i>
              <br>
              <label for="" class="m-0  header-icon-anchor">Signout</label>
            </button>
          </form>
          <!-- End Logout -->
        </div>
        <!-- End Header -->

        <!-- Right navbar links -->
        <ul class="navbar-nav col d-none">

          <li class="nav-item">
            <div class="user-panel d-flex">
              <!-- User -->
              <div class="info">
                <a href="#" class="d-block nav-link text-dark">{{ auth('admin')->user()->name }}
                  <span><small class="text-success"> ( {{ auth('admin')->user()->role_type }} )</small></span>
                </a>
              </div>
              <!-- End User -->
            </div>
          </li>


          <li class="nav-item">
            <div class="user-panel d-flex">


              <div class="info">
                <!-- Setting -->
          <li class="nav-item">
            <i class="fa fa-cog header-icon"></i>
            <label for="">Setting</label>
          </li>
          <!-- End Setting -->

          <form action="{{ url('admin/logout') }}" method="post">
            @csrf
            <button class="bg-transparent border-0 d-block nav-link text-dark" title="Logout">
              <i class="fas  fa-power-off header-icon"></i>
            </button>
            <label for="">Signout</label>
          </form>
      </div>
      </div>
  </div>
  </li>

  </ul>
  </nav>
  <!-- /.navbar -->

  <!-- Main Sidebar Container -->
  <x-admin.sidebar />

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    @yield("content")

    <!-- Change Password -->
    <!-- Modal -->
    <div class="modal fade" id="change-password-modal" data-backdrop="static" data-keyup="false" role="dialog">
      <div class="modal-dialog  modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-theme">
            <h5 class="modal-title" id="change_password_label">Change Password</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="ajax-msg"></div>
            <form action="#" method="post" id="change-password-form">
              @csrf
              <!-- Change Password -->
              <div class="row">

                <!-- Hidden Username Field -->
                <div class="form-group" style="display:none;">
                  <label for="username">Username</label>
                  <input type="text" name="username" id="username" autocomplete="username" value="{{ auth('admin')->user()->name }}">
                </div>


                <!-- Old Password -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">Old Password</label>
                    <input type="password" placeholder="Enter old password" name="old_password" class="form-control" required autocomplete="current-password">
                  </div>
                </div>
                <!-- End Old Password -->

                <!-- New Password -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">New Password</label>
                    <input type="password" placeholder="Enter new password" name="new_password" class="form-control" id="new_password" required autocomplete="new-password">
                  </div>
                </div>
                <!-- End New Password -->

                <!-- Confirm New Password -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">Confirm New Password</label>
                    <input type="password" placeholder="Enter confirm new password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                  </div>
                </div>
                <!-- End New Password -->

                <!-- Submit Form -->
                <div class="col-md-12">
                  <div class="text-center">
                    <button class="btn bg-theme btn-sm">Submit</button>
                  </div>
                </div>
                <!-- End Submit Form -->
              </div>
              <!-- End Change Password -->
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- End Reset Password -->

    <!-- Update Game Challenge Result -->
    <div class="modal fade" id="update-game-challenge-result-modal" data-bs-backdrop="static" data-bs-keyup="false" role="dialog">
      <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
          <div class="modal-header bg-theme">
            <h5 class="modal-title">Update Challenge</h5>
            <button type="button" class="close" data-bs-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="ajax-msg"></div>
            <form action="#" method="post" id="update-game-challenge-result-form">
              @csrf
              <!-- Change Password -->
              <div class="row">
                <!-- GameID -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">GameID</label>
                    <input type="text" name="game_challenge_id" id="game_challenge_id" class="form-control" required readonly>
                  </div>
                </div>
                <!-- End GameID -->

                <!-- Choose Action -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">Choose Action</label>
                    <select name="action" class="form-control" required>
                      <option value="">Choose...</option>
                      <option value="challenger_win">Challenger Win</option>
                      <option value="opponent_win">Opponent Win</option>
                      <option value="cancel">Cancel</option>
                      <option value="suspended">Suspended</option>
                    </select>
                    <small class="text-muted">DK Sync / DK Remote games use the same actions; result is pushed via ResultUpdateRequest.</small>
                  </div>
                </div>
                <!-- End Choose Action -->

                <!-- Penalty -->
                <div class="col-md-12">
                  <div class="form-group">
                    <label for="">Penalty <small class="text-danger">( Optional )</small> </label>
                    <select name="penalty" class="form-control">
                      <option value="0">No</option>
                      <option value="1">Challenger</option>
                      <option value="2">Opponent</option>
                    </select>
                  </div>
                </div>
                <!-- End Penalty -->

                <!-- Submit Form -->
                <div class="col-md-12">
                  <div class="text-center">
                    <button class="btn bg-theme btn-sm my-2">Decision Confirmed</button>
                  </div>
                  <div class="col-md-12 text-center">
                    <small class="text-muted">
                      Note:
                      Please remeber that once submitted you cannot reverse your action.
                    </small>
                  </div>
                </div>
                <!-- End Submit Form -->
              </div>
              <!-- End Change Password -->
            </form>
          </div>
        </div>
      </div>
    </div>
    <!-- End Update Game Challenge Result -->

  </div>
  <!-- /.content-wrapper -->


  </div>
  <!-- ./wrapper -->

  <!-- jQuery -->
  <script src="{{ asset('admin-assets') }}/plugins/jquery/jquery.min.js"></script>
  <!-- jQuery UI 1.11.4 -->
  <script src="{{ asset('admin-assets') }}/plugins/jquery-ui/jquery-ui.min.js"></script>
  <!-- Resolve conflict in jQuery UI tooltip with Bootstrap tooltip -->
  <script>
    $.widget.bridge('uibutton', $.ui.button)
  </script>
  <!-- Bootstrap 4 -->
  <script src="{{ asset('admin-assets') }}/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>

  <!-- Select2 -->
  <script src="{{ asset('admin-assets') }}/plugins/select2/js/select2.full.min.js"></script>

  <!-- SweetAlert -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- DataTables  & Plugins -->
  <script src="{{ asset('admin-assets') }}/plugins/datatables/jquery.dataTables.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-responsive/js/dataTables.responsive.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-responsive/js/responsive.bootstrap4.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-buttons/js/dataTables.buttons.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-buttons/js/buttons.bootstrap4.min.js"></script>

  <script src="{{ asset('admin-assets') }}/plugins/datatables-buttons/js/buttons.html5.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-buttons/js/buttons.print.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/datatables-buttons/js/buttons.colVis.min.js"></script>

  <!-- ChartJS -->
  <script src="{{ asset('admin-assets') }}/plugins/chart.js/Chart.min.js"></script>
  <!-- Sparkline -->
  <!-- <script src="{{ asset('admin-assets') }}/plugins/sparklines/sparkline.js"></script> -->
  <!-- JQVMap -->
  <!-- <script src="{{ asset('admin-assets') }}/plugins/jqvmap/jquery.vmap.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/jqvmap/maps/jquery.vmap.usa.js"></script> -->
  <!-- jQuery Knob Chart -->
  <script src="{{ asset('admin-assets') }}/plugins/jquery-knob/jquery.knob.min.js"></script>
  <!-- daterangepicker -->
  <script src="{{ asset('admin-assets') }}/plugins/moment/moment.min.js"></script>
  <script src="{{ asset('admin-assets') }}/plugins/daterangepicker/daterangepicker.js"></script>
  <!-- Tempusdominus Bootstrap 4 -->
  <script src="{{ asset('admin-assets') }}/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
  <!-- Summernote -->
  <script src="{{ asset('admin-assets') }}/plugins/summernote/summernote-bs4.min.js"></script>
  <!-- overlayScrollbars -->
  <script src="{{ asset('admin-assets') }}/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
  <!-- AdminLTE App -->
  <script src="{{ asset('admin-assets') }}/dist/js/adminlte.js"></script>
  <!-- AdminLTE for demo purposes -->
  <!-- <script src="{{ asset('admin-assets') }}/dist/js/demo.js"></script> -->
  <!-- AdminLTE dashboard demo (This is only for demo purposes) -->
  <script src="{{ asset('admin-assets') }}/dist/js/pages/dashboard.js"></script>

  <script src="https://code.jquery.com/ui/1.13.0/jquery-ui.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.ckeditor.com/4.14.0/full/ckeditor.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.21.0/jquery.validate.min.js"></script>

  <!-- JS for DataTables, Buttons, and export functionality -->
<script src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.3/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.0/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.3/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.3/js/buttons.print.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.0/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.3/js/buttons.html5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>

  @vite(['resources/js/app.js']) <!-- Include JavaScript -->

  <script>
// Pusher
    document.addEventListener('DOMContentLoaded', () => {
      Echo.channel('demo-channel')
      .listen('DemoEvent', (e) => {
               try{
                table.ajax.reload();
            }
            catch(error){
                console.log("Table not found")
            }
            
            try{
                Livewire.dispatch('refreshComponent')
            }
            catch(error){
                console.log("Livewire not found")
            }
            
          
        });
      });
    // End Pusher
    
    // Livewire
       document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM fully loaded and parsed');

            document.addEventListener('livewire:init', function() {
                console.log('Livewire is init');
                
                Livewire.dispatch('refreshComponent')
            });
        });
    // End Livewire


    //  ********* Validations
    $(document).on('change', 'input[type="text"]', function() {
      if (/^\s/.test($(this).val()))
        $(this).val('');
    });

    /******************************* Numbers Only *******************************/

    jQuery.fn.ForceNumericOnly = function() {
      return this.each(function() {
        $(this).keydown(function(e) {
          var key = e.charCode || e.keyCode || 0;
          // allow backspace, tab, delete, enter, arrows, numbers and keypad numbers ONLY
          // home, end, period, and numpad decimal
          return (key == 8 || key == 9 || key == 13 || key == 46 || key == 110 || /*key == 190 ||*/ (key >= 35 && key <= 40) || (key >= 48 && key <= 57) || (key >= 96 && key <= 105));
        });
      });
    };

    //==> Add class to form input 

    $(".numberOnly").ForceNumericOnly();

    //==> End  

    /******************************* End Numbers Only *******************************/

    /******************************* Aplha Only *******************************/

    $('.alphaOnly').bind('keyup blur', function() {
      var node = $(this);
      node.val(node.val().replace(/[^a-zA-Z ]/g, ''));
    });

    /******************************* End Aplha Only *******************************/
    //  ********* End Validations

    // Get Cities
    function getCities(state_id) {
      $.ajax({
        type: "get",
        url: "{{ url('getCities') }}",
        dataType: 'json',
        data: {
          "_token": "{{ csrf_token() }}",
          state_id: state_id
        },
        success: function(data) {
          console.log(data);
          if (data.status) {
            $('#cities').html(data.cities);
          } else {
            $('#cities').html('');
          }
        },
        error: function() {
          alert('Some Error Occured.');
        }
      });
    }
    // End Get Cities

    // End Get Localities
    function getLocalities(city_id) {
      $.ajax({
        type: "get",
        url: "{{ url('getLocalities') }}",
        dataType: 'json',
        data: {
          "_token": "{{ csrf_token() }}",
          city_id: city_id
        },
        success: function(data) {
          console.log(data);
          if (data.status) {
            $('#locality').html(data.localities);
          } else {
            $('#locality').html('');
          }
        },
        error: function() {
          alert('Some Error Occured.');
        }
      });
    }
    // End Get Localities

    // Add More

    function removeCloneTemplateRow(el) {
      $(el).parents('.clone-template').remove();

      $.each($('.itinerary'), function(i) {
        var key = i + 1;
        $(this).find('#day').val(key);
        $(this).attr('data-clone-template-id', key)
      });
    }

    var remove_combo_btn_html = '';
    var clone_template_id = 0;

    function add_more(e, type, parent_class) {

      var clone_template = $(e).parents(parent_class).find('.clone-template');
      var last_clone_template_id = clone_template.last().attr('data-clone-template-id');
      var dublicate_clone_template = clone_template.first().clone();

      next_clone_template_id = parseInt(last_clone_template_id) + 1;
      html_remove_current_clone_template_btn = '<span class="remove-clone-template-row fa fa-trash" onclick="removeCloneTemplateRow(this)"></span>';

      modifyCloneTemplate(dublicate_clone_template, next_clone_template_id, type);

      dublicate_clone_template.append(html_remove_current_clone_template_btn);
      clone_template.last().after(dublicate_clone_template);
    }

    function modifyCloneTemplate(dublicate_clone_template, clone_template_id, type) {

      dublicate_clone_template.attr('data-clone-template-id', clone_template_id);
      dublicate_clone_template.find('input[name="product_id"]').remove();
      dublicate_clone_template.find('span.text-danger').html('');

      switch (type) {
        case 'safety_advices':
          dublicate_clone_template.find('#title').attr('name', "safety_advices[" + clone_template_id + "][title]").val('');
          dublicate_clone_template.find('#caution').attr('name', "safety_advices[" + clone_template_id + "][caution]").val('');
          dublicate_clone_template.find('#highlight').attr('name', "safety_advices[" + clone_template_id + "][highlight]").val('');
          dublicate_clone_template.find('#description').attr('name', "safety_advices[" + clone_template_id + "][description]").val('');
          break;

        case 'gallery_images':
          dublicate_clone_template.find('.image').attr('name', "gallery_images[" + clone_template_id + "][image]").val('');
          dublicate_clone_template.find('.alt').attr('name', "gallery_images[" + clone_template_id + "][alt]").val('');
          dublicate_clone_template.find('img').remove();
          dublicate_clone_template.find('.old_image').remove();
          break;

        case 'faq':
          dublicate_clone_template.find('#question').attr('name', "faq[" + clone_template_id + "][question]").val('');
          dublicate_clone_template.find('#answer').attr('name', "faq[" + clone_template_id + "][answer]").val('');
          break;

        case 'video-gallery':
          dublicate_clone_template.find('#youtube_link').attr('name', "video_gallery[" + clone_template_id + "][youtube_link]").val('');
          dublicate_clone_template.find('#title').attr('name', "video_gallery[" + clone_template_id + "][title]").val('');
          dublicate_clone_template.find('#status').attr('name', "video_gallery[" + clone_template_id + "][status]").val('1');
          dublicate_clone_template.find('#order').attr('name', "video_gallery[" + clone_template_id + "][order]").val('');
          break;

        case 'dynamic-content':
          dublicate_clone_template.find('#heading').attr('name', "dynamic_content[" + clone_template_id + "][heading]").val('');
          dublicate_clone_template.find('#description').attr('id', 'description_' + clone_template_id).attr('name', "dynamic_content[" + clone_template_id + "][description]").val('');
          dublicate_clone_template.find('#cke_description').remove();

          setTimeout(() => {
            CKEDITOR.replace('description_' + clone_template_id);
          }, 100);
          break;

        case 'itinerary':
          dublicate_clone_template.find('#day').attr('name', "itinerary[" + clone_template_id + "][day]").val(clone_template_id);
          dublicate_clone_template.find('#title').attr('name', "itinerary[" + clone_template_id + "][title]").val('');
          dublicate_clone_template.find('.itinerary_description').attr('id', 'itinerary_description_' + clone_template_id).attr('name', "itinerary[" + clone_template_id + "][description]").val('');
          dublicate_clone_template.find('#cke_itinerary_description').remove();

          setTimeout(() => {
            CKEDITOR.replace('itinerary_description_' + clone_template_id);
          }, 100);

          break;
      }

    }

    // End Add More

    //  Slug
    $(".slug").keyup(function() {
      var Text = $(this).val();
      var slug = Text.toLowerCase()
        .replace(/ /g, '-')
        .replace(/[^\w-]+/g, '');

      $(".set-slug").val(slug);
    });
    //  End Slug

    $('.select2').select2({
      placeholder: "Choose..."
    });

    // Delete
    $(document).on('click', '.delete-btn', function() {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        showConfirmButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, delete it!'
      }).then((result) => {
        if (result.value == 1) {
          var id = $(this).data('id');
          var type = $(this).data('type');

          delete_url = `{{ url('admin') }}/${type}/${id}`;

          deleteRowAjax(delete_url, this, id = '');
        }
      });
    });

    function deleteRowAjax(delete_url, row, id) {
      $.ajax({
        url: delete_url,
        type: 'delete',
        data: {
          "id": id,
          "_token": "{{ csrf_token() }}"
        },
        beforeSend: function() {

        },
        success: function(res) {
          if (res.status) {

            $(row).parent().parent().remove();
            Swal.fire(
              'Deleted!',
              res.message,
              'success'
            );
          } else {
            Swal.fire(
              'Error!',
              res.message,
              'error'
            );
          }
        },
        error: function() {
          Swal.fire(
            'Error!',
            'Some error occured.',
            'error'
          );
        }
      });
    }
    // End Delete

    $(document).ready(function() {
      $("#search-menu-input").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".sidebar-menus > li").filter(function() {
          $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)

        });
      });
    });


    // Text Copy Functionality
    $(document).on('click', '.copy-btn', function() {
      var copyText = $(this).parents('.copy-text-container').find('.copy-text');

      navigator.clipboard.writeText(copyText.text())

      alert("Copied the generated link. ");
    })
    // End Text Copy Functionality

    /** Change Password */
    $('.change-password-btn').on('click', function() {
      $('#change-password-modal').modal('show')
    })

    $('#change-password-modal .close').on('click', function() {
      $('#change-password-form')[0].reset()
      $('#change-password-modal label.error').remove()
    })

    // Change Form Submit Via Ajax
    /** */
    $("#change-password-form").validate({
      rules: {
        old_password: {
          required: true,
          minlength: 5
        },
        new_password: {
          required: true,
          minlength: 5
        },
        password_confirmation: {
          required: true,
          equalTo: "#new_password"
        }
      },
      messages: {
        old_password: {
          required: "This field is required.",
          minlength: "Please enter at least 6 characters."
        }
      },
      submitHandler: function(form) {
        // Custom action instead of form.submit()
        event.preventDefault(); // Prevent the default form submission

        // If you want to handle the form via AJAX, you can do something like:

        $.ajax({
          url: "{{ url('admin/change-password') }}",
          type: 'POST',
          dataType: 'json',
          data: $(form).serialize(),
          success: (res) => {
            if (res.status) {
              // swal.fire('Success', res.message, 'success')
              $('#change-password-modal .ajax-msg').html(`<div class='alert alert-success'>${res.message}</div>`)
              $('#change-password-form')[0].reset()
            } else {
              $('#change-password-modal .ajax-msg').html(`<div class='alert alert-danger'>${res.message}</div>`)
            }
          },
          error: (res) => {
            swal.fire('Error', 'Some error occured', 'error')
          }
        });

      }
    });
    /** */
    // End Change Form Submit Via Ajax

    /** End Change Password */

    /** */
    $(document).on('click', '.game-challenge-action-btn', function() {
      game_id = $(this).data('game-id')

      game_challenge_result = $('form#update-game-challenge-result-form')

      game_challenge_result.find('input#game_challenge_id').val(game_id)

      $('#update-game-challenge-result-modal').modal('show')
    })
    /** */

    $(document).on('click', '.ludo-king-result-view-btn', function() {
      const $btn = $(this)
      /* jQuery .data camelCases data-* keys; read attribute so hyphenated IDs work */
      const ludo_king_game_id = $btn.attr('data-ludo-king-game-id') || $btn.data('ludoKingGameId')
      if (!ludo_king_game_id) {
        swal.fire('Error', 'Missing stored room / game id for this challenge', 'error')
        return
      }
      $.ajax({
          url: "{{ url('admin/view-ludo-king-result') }}",
          type: 'GET',
          dataType: 'json',
          headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
          },
          data: {
            ludo_king_game_id : ludo_king_game_id,
            game_uid: $btn.attr('data-game-id') || ''
          },
          success: (res) => {
            if (res.status) {
                $btn.closest('td').find('.ludo-shree-result-view').html(res.view);

                const gs = String(res.data.game_status || res.data.status || '').toLowerCase();
                if (res.auto_settled || gs === 'finished' || gs === 'destroyed' || gs === 'completed') {
                  $btn.remove();
                }

            } else {
              swal.fire('Error', res.message || 'Request failed', 'error')
            }
          },
          error: (xhr) => {
            const msg = xhr.responseJSON && xhr.responseJSON.message
              ? xhr.responseJSON.message
              : (xhr.status === 419 ? 'Session expired — refresh and try again' : 'Unable to fetch result (network or login issue)')
            swal.fire('Error', msg, 'error')
          }
        });

    })

    // Change Form Submit Via Ajax
    /** */
    $("#update-game-challenge-result-form").validate({
      rules: {
        game_challenge_id: {
          required: true
        },
        action: {
          required: true
        }
      },
      messages: {
        game_challenge_id: {
          required: "This field is required."
        }
      },
      submitHandler: function(form) {
        // Custom action instead of form.submit()
        event.preventDefault(); // Prevent the default form submission

        // If you want to handle the form via AJAX, you can do something like:

        $.ajax({
          url: "{{ url('admin/update-game-challenge-result') }}",
          type: 'POST',
          dataType: 'json',
          data: $(form).serialize(),
          beforeSend: (res) => {
            $('#update-game-challenge-result-form button').prop('disabled', true)
          },
          success: (res) => {
            $('#update-game-challenge-result-form button').prop('disabled', false)
            
            if (res.status) {
              swal.fire('Success', res.message, 'success')
              $('#update-game-challenge-result-modal').modal('hide')
              $(form)[0].reset()
              table.ajax.reload();
            } else {
              swal.fire('Error', res.message, 'error')
            }
          },
          error: (res) => {
            swal.fire('Error', 'Some error occured', 'error')
          }
        });

      }
    });
    /** */
    // End Change Form Submit Via Ajax
  </script>

  @yield("content_js")
  @yield("script")
  @yield("component-script")


  <script>
    var searchTimer;
    $('#serach-datatable').on('input', function() {
      var val = $(this).val();
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function() {
        if (typeof table !== 'undefined') {
          table.search(val).draw();
        }
      }, 350);
    });

    /** Please Wait Popup */
    function please_wait(){
      // Show the initial "Please wait" SweetAlert with loading spinner
Swal.fire({
    title: 'Please wait',
    text: 'Processing your request...',
    allowOutsideClick: false,
    showConfirmButton: false,
    willOpen: () => {
        Swal.showLoading();
    }
});

    }
    /** Please Wait Popup */
 </script>

<script>
    function confirmLogout() {
        Swal.fire({
            title: 'Are you sure?',
            text: "You will be logged out.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, log me out'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('logout-form').submit();
            }
        });
    }

    /** Delete Game Challenge */
    $(document).on('click', '.game-challenge-delete-btn',  function(){
      game_id = $(this).data('game-id')
        Swal.fire({
              title: 'Are you sure?',
              text: "You action",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Yes, delete it.'
          }).then((result) => {
              if (result.isConfirmed) {
                  $.ajax({
                    url : "{{ url('admin/delete-game-challenge') }}",
                    method: 'POST',
                    dataType: 'json',
                    data : {
                      _token : "{{ csrf_token() }}",
                      game_id : game_id
                    },
                    beforeSend: (res) => {
                      $(this).prop('disabled', true)
                    },
                    success: (res) => {
                      $(this).prop('disabled', false)
                      if(res.status){
                        table.ajax.reload();
                        swal.fire('Success', res.message, 'success')
                      }else{
                        swal.fire('Error', res.message, 'error')
                      }
                    },
                    error: (res) => {
                      
                      swal.fire('Error', 'Some error occured', 'error')
                    },
                  })
              }
          });
      })

</script>

@if(session()->has('back_msg'))
    <script>
        Swal.fire('Success', '{{ session()->get('back_msg') }}', 'success');
    </script>
@endif
</body>

</html>