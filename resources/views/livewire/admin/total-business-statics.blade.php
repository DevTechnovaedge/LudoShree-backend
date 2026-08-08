<div>
@can('permissions', ['all_users', 'view'])
    <div class="total-business-statics-section">
      <div class="card">
        <div class="card-header bg-theme">
          <div class="text-left">
            <h5 class="m-0">Total Business Statics</h5>
          </div>
        </div>
        <div class="card-body p-4">
          <div class="row align-items-center gx-4">
            <!-- Users -->
            <x-admin.pages.dashboard-cards count="{{ $users_count ?? 0 }}" card_label="User" url="{{ url('admin/users') }}" permission="all_users" bg="bg-green" bg_dark="bg-green-dark" />
            <!-- End Users -->

            <!-- Successful Games -->
            <x-admin.pages.dashboard-cards card_label="Successful Games" count="{{ $successful_games_count ?? 0 }}" url="{{ url('admin/game-challenges?filter=complete_challenges') }}" permission="all_users" bg="bg-orange" bg_dark="bg-orange-dark" />
            <!-- End Successful Games -->

            <!-- Deposit -->
            <x-admin.pages.dashboard-cards card_label="Deposit" count="{{ $deposit_amount_count ?? 0 }}" url="{{ url('admin/transactions?filter=deposits') }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End Deposit -->

            <!-- Withdrawal -->
            <x-admin.pages.dashboard-cards card_label="Withdrawal" count="{{ $withdrawal_amount_count ?? 0 }}" url="{{ url('admin/transactions?filter=withdrawals') }}" permission="all_users" bg="bg-brown" bg_dark="bg-brown-dark" />
            <!-- End Withdrawal -->

            <!-- Refer Commission -->
            <x-admin.pages.dashboard-cards card_label="Refer Commission" count="{{ $total_refer_commission_amount ?? 0 }}" url="{{ url('admin/refer-commissions') }}" permission="all_users" bg="bg-silver" bg_dark="bg-silver-dark" />
            <!-- End Refer Commission -->

            <!-- Win Wallet -->
            <x-admin.pages.dashboard-cards card_label="Win Wallet"  count="{{ number_format($total_win_amount ?? 0, 2, '.', '') }}" url="{{ url('admin/win-credit-and-debit') }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End Win Wallet -->

            <!-- Game Wallet -->
            <x-admin.pages.dashboard-cards card_label="Game Wallet" count="{{ number_format($total_game_amount ?? 0, 2, '.', '') }}" url="{{ url('admin/game-credit-and-debit') }}" permission="all_users" bg="bg-red" bg_dark="bg-red-dark" />
            <!-- End Game Wallet -->

            <!-- Admin Commission -->
            <x-admin.pages.dashboard-cards card_label="Admin Commission" count="{{ $total_admin_commission_amount ?? 0 }}" url="{{ url('admin/game-commissions') }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End Admin Commission -->

            <!-- Classic -->
            <x-admin.pages.dashboard-cards card_label="Classic" count="{{ $classic_games_count ?? 0 }}" url="{{ url('admin/game-challenges?filter=classic') }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End Classic -->

            <!-- Ulta Ludo -->
            <x-admin.pages.dashboard-cards card_label="Ulta Ludo" count="{{ $ulta_games_count ?? 0 }}"  url="{{ url('admin/game-challenges?filter=ulta-ludo') }}" permission="all_users" bg="bg-brown" bg_dark="bg-brown-dark" />
            <!-- End Ulta Ludo -->

            <!-- Success Game Amount -->
            <x-admin.pages.dashboard-cards card_label="Success Game Amount" count="{{ $successful_game_amount_count ?? 0 }}"  url="{{ url('admin/users') }}" permission="all_users" bg="bg-orange" bg_dark="bg-orange-dark" />
            <!-- End Success Game Amount -->

            <!-- KYC Completed -->
            <x-admin.pages.dashboard-cards card_label="KYC Completed" count="{{ $users_kyc_complete_count ?? 0 }}" url="{{ url('admin/users?filter=kyc_complete') }}" permission="all_users" bg="bg-pink" bg_dark="bg-pink-dark" />
            <!-- End KYC Completed -->

            <!-- KYC Pending -->
            <x-admin.pages.dashboard-cards card_label="KYC Pending"  count="{{ $users_kyc_pending_count ?? 0 }}" url="{{ url('admin/users?filter=kyc_pending') }}" permission="all_users" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End KYC Pending -->

            <!-- Cashback -->
            <x-admin.pages.dashboard-cards card_label="Win To Game Cashback"  count="{{ number_format($win_to_game_cashback ?? 0, 2, '.', '') }}" url="{{ url('admin/win-to-game-cashbacks') }}" permission="win_to_game_cashbacks" bg="bg-purple" bg_dark="bg-purple-dark" />
            <!-- End Cashback -->

          </div>
        </div>
      </div>
    </div>
    @endcan
    </div>