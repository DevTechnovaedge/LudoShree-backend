<div>
@can('permissions', ['all_users', 'view'])
    <div class="today-business-statics-section">
      <div class="card">
        <div class="card-header bg-theme">
          <div class="text-left">
            <div class="row">
              <div class="col-md-10 align-self-center">
                <h5 class="m-0">Today Business Statics <small class="text-white-50">({{ $filter_date }})</small></h5>
              </div>

              <div class="col-md-2">
                <div class="text-end">
                <input
                  type="date"
                  id="filter_date"
                  class="form-control"
                  value="{{ $filter_date }}"
                  max="{{ carbon()->format('Y-m-d') }}"
                  onchange="window.location.href='{{ route('admin::dashboard') }}?stats_date=' + encodeURIComponent(this.value)"
                >
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body p-4">
          <div class="row align-items-center gx-4" wire:key="today-business-stats-{{ $filter_date }}">
            <!-- Users -->
            <x-admin.pages.dashboard-cards count="{{ $users_count ?? 0 }}" card_label="User" url="{{ url('admin/users?filter=today') }}&date={{ $filter_date }}" permission="all_users" bg="bg-green" bg_dark="bg-green-dark" />
            <!-- End Users -->

            <!-- Successful Games -->
            <x-admin.pages.dashboard-cards card_label="Successful Games" count="{{ $successful_games_count ?? 0 }}" url="{{ url('admin/game-challenges?filter=complete_challenges') }}&date={{ $filter_date }}" permission="all_users" bg="bg-orange" bg_dark="bg-orange-dark" />
            <!-- End Successful Games -->

            <!-- Deposit -->
            <x-admin.pages.dashboard-cards card_label="Deposit" count="{{ $deposit_amount_count ?? 0 }}" url="{{ url('admin/transactions?filter=deposits') }}&date={{ $filter_date }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End Deposit -->

            <!-- Withdrawal -->
            <x-admin.pages.dashboard-cards card_label="Withdrawal" count="{{ $withdrawal_amount_count ?? 0 }}" url="{{ url('admin/transactions?filter=withdrawals') }}&date={{ $filter_date }}" permission="all_users" bg="bg-brown" bg_dark="bg-brown-dark" />
            <!-- End Withdrawal -->

            <!-- Success Game Amount -->
            <x-admin.pages.dashboard-cards card_label="Success Game Amount" count="{{ $successful_game_amount_count ?? 0 }}" url="{{ url('admin/users') }}?date={{ $filter_date }}" permission="all_users" bg="bg-orange" bg_dark="bg-orange-dark" />
            <!-- End Success Game Amount -->

            <!-- Win Wallet -->
            {{-- <x-admin.pages.dashboard-cards card_label="Win Wallet" count="{{ $win_wallet_amount ?? 0 }}" url="{{ url('admin/win-credit-and-debit') }}?date={{ $filter_date }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" /> --}}
            <!-- End Win Wallet -->

            <!-- Game Wallet -->
            {{--  <x-admin.pages.dashboard-cards card_label="Game Wallet" count="{{ $game_wallet_amount ?? 0 }}" url="{{ url('admin/game-credit-and-debit') }}?date={{ $filter_date }}" permission="all_users" bg="bg-red" bg_dark="bg-red-dark" />  --}}
            <!-- End Game Wallet -->

             <!-- KYC Completed -->
             <x-admin.pages.dashboard-cards card_label="KYC Completed" count="{{ $users_kyc_complete_count ?? 0 }}" url="{{ url('admin/users?filter=kyc_complete') }}&date={{ $filter_date }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End KYC Completed -->

            <!-- Classic -->
            <x-admin.pages.dashboard-cards card_label="Classic" count="{{ $classic_games_count ?? 0 }}" url="{{ url('admin/game-challenges?filter=classic') }}&date={{ $filter_date }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End Classic -->

            <!-- Ulta Ludo -->
            <x-admin.pages.dashboard-cards card_label="Ulta Ludo" count="{{ $ulta_games_count ?? 0 }}" url="{{ url('admin/game-challenges?filter=ulta-ludo') }}&date={{ $filter_date }}" permission="all_users" bg="bg-brown" bg_dark="bg-brown-dark" />
            <!-- End Ulta Ludo -->

            <!-- Refer Commission -->
            <x-admin.pages.dashboard-cards card_label="Refer Commission" count="{{ $total_refer_commission_amount ?? 0 }}" url="{{ url('admin/refer-commissions') }}?date={{ $filter_date }}" permission="all_users" bg="bg-silver" bg_dark="bg-silver-dark" />
            <!-- End Refer Commission -->

            <!-- Admin Commission -->
            <x-admin.pages.dashboard-cards card_label="Admin Commission" count="{{ $total_admin_commission_amount ?? 0 }}" url="{{ url('admin/game-commissions') }}?date={{ $filter_date }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End Admin Commission -->

            <!-- KYC Pending -->
            <x-admin.pages.dashboard-cards card_label="KYC Pending" count="{{ $users_kyc_pending_count ?? 0 }}" url="{{ url('admin/users?filter=kyc_pending') }}&date={{ $filter_date }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" />

            <!-- Cashback -->
            <x-admin.pages.dashboard-cards card_label="Win To Game Cashback"  count="{{ number_format($win_to_game_cashback ?? 0, 2, '.', '') }}" url="{{ url('admin/win-to-game-cashbacks') }}?date={{ $filter_date }}" permission="win_to_game_cashbacks" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End Cashback -->
          </div>
        </div>
      </div>
    </div>

    @else
    <div class="row">
      <div class="col-md-12">
        <div class="text-center">
          <h4>Welcome</h4>
        </div>
      </div>
    </div>
    @endcan
    </div>