@push('style')
<style>
    .form-control:focus {
        border-color: #28a745;
        box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
    }
</style>
@endpush

<aside class="main-sidebar">
    <!-- Brand Logo -->
    <a href="{{ url('admin') }}" class="nav-link bg-theme w-100" style=" display: flex; align-items: center;">
        <img src="{{ asset('admin-assets') }}/dist/img/AdminLTELogo.png" alt="AdminLTE Logo" class="brand-image img-circle elevation-3" style="opacity: .8" width="40px">
        <span class="pl-3 brand-text font-weight-light text-white">{{ $sitesetting_details->site_name }}</span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user panel (optional) -->

        <!-- Sidebar Menu -->
        <nav class="mt-2">
            <div class="nav-item">
                <input type="text" id="search-menu-input" placeholder="&#128270; Serach...." class="form-control mb-2">
            </div>

            <ul class="nav nav-pills nav-sidebar flex-column sidebar-menus" data-widget="treeview" role="menu" data-accordion="false">

                <li class="nav-item">
                    <a href="{{url('admin')}}" class="nav-link {{ request()->is('admin') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-tachometer-alt"></i>
                        <p>
                            Dashboard
                        </p>
                    </a>
                </li>

                <!-- User Management -->
                @can('permissions', ['all_users', 'view'], ['zero_balance_users', 'view'], ['kyc_pending_users', 'view'], ['kyc_complete_users', 'view'])
                <li class="nav-item {{ ( request()->is('admin/users') || request()->is('admin/users/*') )? ' menu-is-opening menu-open ' : '' }}">
                    <a href="{{ url('admin/users') }}" class="nav-link">
                        <i class="fas fa-users nav-icon"></i>
                        <p>
                            User Management
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions', ['all_users', 'view'])
                        <li class="nav-item {{ (request()->is('admin/users','admin/users/*') && request()->filter == '') ? 'active' : '' }}">
                            <a href="{{ url('admin/users') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>All Users</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['zero_balance_users', 'view'])
                        <li class="nav-item {{ (request()->filter == 'zero_balance_users') ? 'active' : '' }}">
                            <a href="{{ url('admin/users?filter=zero_balance_users') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Zero Balance User</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['kyc_pending_users', 'view'])
                        <li class="nav-item {{ (request()->filter == 'kyc_pending') ? 'active' : '' }}">
                            <a href="{{ url('admin/users?filter=kyc_pending') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KYC Pending</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['kyc_complete_users', 'view'])
                        <li class="nav-item {{ (request()->filter == 'kyc_complete') ? 'active' : '' }}">
                            <a href="{{ url('admin/users?filter=kyc_complete') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>KYC Complete</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End User Management -->

                <!-- Game Management -->
                @can('permissions', ['pending_challenges', 'view'], ['live_challenges', 'create'], ['complete_challenges', 'view'], ['uncomplete_challenges', 'view'])
                <li class="nav-item {{ (request()->filter == 'pending_challenges') || (request()->filter == 'live_challenges') || (request()->filter == 'complete_challenges') || (request()->filter == 'uncomplete_challenges') ? ' menu-is-opening menu-open ' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="fa fa-dice nav-icon"></i>
                        <p>
                            Game Management
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions', ['pending_challenges', 'view'])
                        <li class="nav-item {{ (request()->filter == 'pending_challenges') ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=pending_challenges') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Pending Challenge
                                    <small>( {{ site_setting()->pending_challenges_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['live_challenges', 'view'])
                        <li class="nav-item {{ ( request()->filter == 'live_challenges' ) ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=live_challenges') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Live Challenge
                                    <small>( {{ site_setting()->live_challenges_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['complete_challenge', 'view'])
                        <li class="nav-item {{ request()->filter == 'complete_challenges' ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=complete_challenges') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Complete Challenge
                                    <small>( {{ site_setting()->complete_challenges_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['uncomplete_challenge', 'view'])
                        <li class="nav-item {{ request()->filter == 'uncomplete_challenges' ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=uncomplete_challenges') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Uncomplete Challenge
                                    <small>( {{ site_setting()->uncomplete_challenges_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End Game Management -->

                  <!-- Pending Game Manage -->
                  @can('permissions', ['uncomplete_games', 'view'], ['uncomplete_cancel_games', 'view'], ['dispute_games', 'view'])
                <li class="nav-item {{ (request()->filter == 'uncomplete_games') || (request()->filter == 'uncomplete_cancel_games') || (request()->filter == 'dispute_games') ? ' menu-is-opening menu-open ' : '' }}">
                    <a href="{{ url('admin') }}" class="nav-link">
                        <i class="fas fa-dice-six nav-icon"></i>
                        <p>
                            Pending Game Manage
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions', ['uncomplete_games', 'view'])
                        <li class="nav-item  {{ request()->filter == 'uncomplete_games' ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=uncomplete_games') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Uncomplete Game
                                    <small>( {{ site_setting()->uncomplete_games_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['uncomplete_cancel_games', 'view'])
                        <li class="nav-item  {{ request()->filter == 'uncomplete_cancel_games' ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=uncomplete_cancel_games') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Uncomplete Cancel Game
                                    <small>({{ site_setting()->cancel_challenges_count }})</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['dispute_games', 'view'])
                        <li class="nav-item  {{ request()->filter == 'dispute_games' ? 'active' : '' }}">
                            <a href="{{ url('admin/game-challenges?filter=dispute_games') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>
                                    Dispute Game
                                    <small>( {{ site_setting()->dispute_games_count }} )</small>
                                </p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End Pending Game Manage -->

                <!-- Wallet Management -->
                @can('permissions', ['game_credit_and_debit', 'view'], ['win_credit_and_debit', 'view'], ['game_ledger', 'view'])
                <li class="nav-item {{ ( request()->is('admin/game-credit-and-debit') || request()->is('admin/win-credit-and-debit') || request()->is('admin/game-ledger') ) ? ' menu-is-opening menu-open ' : '' }}">
                    <a href="#" class="nav-link">
                        <i class="fas fa-wallet nav-icon"></i>
                        <p>
                            Wallet Management
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions', ['game_credit_and_debit', 'view'])
                        <li class="nav-item {{ request()->is('admin/game-credit-and-debit') ? 'active' : '' }}">
                            <a href="{{ url('admin/game-credit-and-debit') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Game Credit & Debit</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['win_credit_and_debit', 'view'])
                        <li class="nav-item {{ request()->is('admin/win-credit-and-debit') ? 'active' : '' }}">
                            <a href="{{ url('admin/win-credit-and-debit') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Win Credit & Debit</p>
                            </a>
                        </li>
                        @endcan
                        
                        @can('permissions', ['game_ledger', 'view'])
                        <li class="nav-item {{ request()->is('admin/game-ledger') ? 'active' : '' }}">
                            <a href="{{ url('admin/game-ledger') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Game Ledger</p>
                            </a>
                        </li>
                        @endcan

                        @can('permissions', ['win_to_game_cashbacks', 'view'])
                        <li class="nav-item {{ request()->is('admin/win-to-game-cashbacks') ? 'active' : '' }}">
                            <a href="{{ url('admin/win-to-game-cashbacks') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Win to Game Cashbacks</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End Wallet Management -->

                <!-- Payment & Withdrawal -->
                @can('permissions', ['deposit_history', 'view'], ['deposit-requests', 'view'], ['withdrawal', 'view'])
                <li class="nav-item {{ (( request()->filter == 'pending-withdrawals') || request()->filter == 'deposits' ||  ( request()->filter == 'withdrawals') || ( request()->filter == 'deposit-requests' ))? ' menu-is-opening menu-open ' : '' }}">
                    <a href="{{ url('admin') }}" class="nav-link">
                        <i class="fas fa-rupee-sign nav-icon"></i>
                        <p>
                            Payment & Withdrawal
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">

                        @can('permissions', ['deposit-requests', 'view'])
                        <li class="nav-item {{ request()->filter == 'deposit-requests' ? 'active' : '' }}">
                            <a href="{{ url('admin/transactions?filter=deposit-requests') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Deposit Requests</p>
                            </a>
                        </li>
                        @endcan

                        @can('permissions', ['withdrawal', 'view'])
                        <li class="nav-item {{ request()->filter == 'pending-withdrawals' ? 'active' : '' }}">
                            <a href="{{ url('admin/transactions?filter=pending-withdrawals') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Withdrawals Request</p>
                            </a>
                        </li>
                        @endcan

                        @can('permissions', ['deposit_history', 'view'])
                        <li class="nav-item {{ request()->filter == 'deposits' ? 'active' : '' }}">
                            <a href="{{ url('admin/transactions?filter=deposits') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Deposits</p>
                            </a>
                        </li>
                        @endcan

                        @can('permissions', ['withdrawal', 'view'])
                        <li class="nav-item {{ (request()->filter == 'withdrawals') ? 'active' : '' }}">
                            <a href="{{ url('admin/transactions?filter=withdrawals') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Withdrawals</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End  Payment & Withdrawal -->

                <!-- Commission Management -->
                @can('permissions', ['refer_commissions', 'view'], ['game_commission_slot', 'view'], ['user_commissions', 'view'])
                <li class="nav-item {{ ( request()->is('admin/refer-commissions')  ||  request()->is('admin/game-commission-slot') ||  request()->is('admin/user-commissions') ) ? ' menu-is-opening menu-open ' : '' }}">
                    <a href="{{ url('admin') }}" class="nav-link">
                        <i class="fas fa-rupee-sign nav-icon"></i>
                        <p>
                            Commission Management
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        @can('permissions', ['refer_commissions', 'view'])
                        <li class="nav-item {{ request()->is('admin/refer-commissions') ? 'active' : '' }}">
                            <a href="{{ url('admin/refer-commissions') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Refer Commission</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['game_commission_slot', 'view'])
                        <li class="nav-item {{ request()->is('admin/game-commission-slot') ? 'active' : '' }}">
                            <a href="{{ url('admin/game-commission-slot') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Game Commission</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['user_commissions', 'view'])
                        <li class="nav-item {{ request()->is('admin/user-commissions') ? 'active' : '' }}">
                            <a href="{{ url('admin/user-commissions') }}" class="nav-link ">
                                <i class="far fa-circle nav-icon"></i>
                                <p>User Commission</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End Commissions -->

                <!-- App Management -->
                @can('permissions', ['notifications', 'view'], ['faqs', 'view'], ['app_setting', 'view'])
                <li class="nav-item {{ ( request()->is('admin/notifications', 'admin/notifications/*') )? ' menu-is-opening menu-open ' : '' }}">
                    <a href="{{ url('admin/users') }}" class="nav-link">
                        <i class="fas fa-mobile-alt nav-icon"></i>
                        <p>
                            App Management
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">
                        <!-- Notification -->
                        @can('permissions', ['notifications', 'view'])
                        <x-admin.common.sidebar-tab label="Notification" slug="notifications" />
                        @endcan
                        <!-- End Notification -->

                        @can('permissions', ['faqs', 'view'])
                        <li class="d-none nav-item {{ request()->is('admin') ? 'active' : '' }}">
                            <a href="{{ url('admin') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>FAQ's</p>
                            </a>
                        </li>
                        @endcan
                        @can('permissions', ['app_setting', 'view'])
                        <li class="d-none nav-item {{ request()->is('admin') ? 'active' : '' }}">
                            <a href="{{ url('admin') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>App Setting</p>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcan
                <!-- End App Management -->

                <!-- Report and Export -->
                @can('permissions', ['report_and_export', 'view'])
                <li class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="far fa-images nav-icon"></i>
                        <p>Report & Export</p>
                    </a>
                </li>
                @endcan
                <!-- End Report and Export -->

                @can('permissions', ['sliders', 'view'])
                <li class="nav-item">
                    <a href="{{ url('admin/sliders') }}" class="nav-link {{ request()->is('admin/sliders') ? 'active' : '' }}">
                        <i class="far fa-images nav-icon"></i>
                        <p>Slider Management</p>
                    </a>
                </li>
                @endcan



                @can('admin')
                <li class="nav-item {{ request()->segment(2) == 'members' || request()->segment(2) == 'roles' || request()->segment(2) == 'modules'
                              ? 'menu-is-opening menu-open' : '' }}">
                    <a href="{{ url('admin/members') }}" class="nav-link">
                        <i class="nav-icon fas fa-lock"></i>
                        <p>
                            Permissions
                            <i class="fas fa-angle-left right"></i>
                        </p>
                    </a>
                    <ul class="nav nav-treeview">

                        @can('permissions', ['members', 'view'])
                        <li class="nav-item {{ request()->segment(2) == 'members' ? 'active' : '' }}">
                            <a href="{{ url('admin/members') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Members</p>
                            </a>
                        </li>
                        @endcan


                        @can('permissions', ['roles', 'view'])
                        <li class="nav-item {{ request()->segment(2) == 'roles' ? 'active' : '' }}">
                            <a href="{{ url('admin/roles') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Roles</p>
                            </a>
                        </li>
                        @endif

                        @can('permissions', ['modules', 'view'])
                        <li class="nav-item {{ request()->segment(2) == 'modules' ? 'active' : '' }}">
                            <a href="{{ route('admin::modules.index') }}" class="nav-link">
                                <i class="far fa-circle nav-icon"></i>
                                <p>Modules</p>
                            </a>
                        </li>
                        @endif

                    </ul>
                </li>
                @endcan

                @can('permissions', ['report', 'view'])
                <li class="nav-item">
                    <a href="{{url('admin/report')}}" class="nav-link {{ request()->is('admin/report') ? 'active' : '' }}">
                        <i class="far fa-circle nav-icon"></i>
                        <p>Report</p>
                    </a>
                </li>
                @endif

                @can('permissions', ['pages', 'view'])
                <li class="nav-item">
                    <a href="{{url('admin/pages')}}" class="nav-link {{ request()->is('admin/pages') ? 'active' : '' }}">
                        <i class="far fa-circle nav-icon"></i>
                        <p>Pages</p>
                    </a>
                </li>
                @endif
                
                @can('site_settings')
                <li class="nav-item mb-5">
                    <a href="{{url('admin/sitesettings')}}" class="nav-link {{ request()->is('admin/sitesettings') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-cog"></i>
                        <p>Settings</p>
                    </a>
                </li>
                @endcan

            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>