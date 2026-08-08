<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Financial\TransferCashback;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\User;
use App\Services\LkGameApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminController extends Controller
{
  public function index()
  {
    $statsDate = request()->query('stats_date');
    if ($statsDate && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $statsDate)) {
      $statsDate = null;
    }

    return view('admin.index', [
      'stats_date' => $statsDate ?: carbon()->now()->format('Y-m-d'),
    ]);
  }

  # Update Withdrawal Status
  public function update_transaction_status()
  {
    $arr          = [];
    $id           =  request()->id;
    $type         =  request()->type;
    $actionType   =  request()->actionType;
    $remark       =  request()->remark;

    $data         = [];
    $message      = 'Nothing updated';

    $transaction    = Transaction::whereId($id)->first();
    $user                         = User::find($transaction->user_id);

    if ($type == 'withdrawal'):
      switch ($actionType):
        case 'approve':
          $transaction->status  =  1;
          $transaction->save();

          $message              = 'Transaction approved successfully';

          # Withdrawal Wallet and History
          # ===========================================================================
          #   End Notification
          # ===========================================================================
          # Wallet Update
          Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' =>  1]);
          # End Wallet Update

          # ===========================================================================
          #   Notification
          # ===========================================================================
          # Notification
          $notification_title     = 'Withdrawal Successfully';
          $notification_body      = 'Amount withdrawal successfully.';
          $notification_type      =  'withdrawal';

          safe_notify(
            $user->fcm_device_token,
            $notification_title,
            $notification_body,
            $notification_type,
            $transaction->user->id,
            ['transaction_id' => $transaction->id, 'context' => 'admin_withdrawal_approve']
          );
          # Notification
          # ===========================================================================
          #   End Notification
          # ===========================================================================
          # End Withdrawal Wallet and History
          break;

        case 'reject':
          $transaction->remark  =  $remark;
          $transaction->status  =  3;
          $transaction->save();

          $message          = 'Transaction rejected successfully';

          # Refund Withdrawal Amount
            $user             = User::find($transaction->user_id);
            $user->win_wallet_amount  = $user->win_wallet_amount + $transaction->amount;
           
            Wallet::whereTransactionId($transaction->id)->update(['type' => 'credit', 'remark' => 'Rejected your withdrawal', 'win_and_game_total_amount' => $user->total_wallet_amount, 'status' =>  2]);

            //  # Wallet Update
              // Wallet::create([
              //   'transaction_id'         =>  $transaction->id,
              //   'user_id'               =>  $user->id,
              //   'type'                  =>  'credit',
              //   'wallet_type'           =>  'win',
              //   'remark'                =>  "Withdrawal Fund Refunded",
              //   'amount'                =>  $transaction->amount,
              //   'total_balance'         =>  $user->total_wallet_amount + $transaction->amount,
              //   'status'                =>  0
              // ]);
              $user->save();
            # End Wallet Update
          # End Refund Withdrawal Amount


          break;

        default:
          $transaction->status  =  0;
          $transaction->save();
          break;
      endswitch;
    endif;

    if ($type == 'deposit'):
      switch ($actionType):
        case 'approve':
          $transaction->status            =  1;
          $transaction->save();

          $user                           = User::find($transaction->user_id);

          $total_game_wallet_amount      = $user->game_wallet_amount + $transaction->amount;
          $user->game_wallet_amount      = $total_game_wallet_amount;
         
          $message              = 'Transaction approved successfully';

          # Withdrawal Wallet and History
        
          # Wallet Update

          // $wallet_record =  Wallet::whereTransactionId($transaction->id)->first();
          Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' =>  1]);
          // Wallet::whereUserId($transaction->user_id)->whereWalletType('game')->whereType('credit')->where('id', '>', $wallet_record->id)->increment('win_and_game_total_amount', $transaction->amount);
         # End Wallet Update
           $user->save();
          # End Wallet Update

          # ===========================================================================
          #   Notification
          # ===========================================================================
          # Notification
          $notification_title     = 'Deposit Fund Approved';
          $notification_body      = 'Amount deposited successfully.';
          $notification_type      =  'deposit';

          safe_notify(
            $user->fcm_device_token,
            $notification_title,
            $notification_body,
            $notification_type,
            $transaction->user->id,
            ['transaction_id' => $transaction->id, 'context' => 'admin_deposit_approve']
          );
          # Notification
          # ===========================================================================
          #   End Notification
          # ===========================================================================
          # End Withdrawal Wallet and History
          break;

        case 'reject':
          $transaction->remark  =  $remark;
          $transaction->status  =  3;
          $transaction->save();

          Wallet::whereTransactionId($transaction->id)->update(['win_and_game_total_amount' => $user->total_wallet_amount, 'status' =>  2]);
          
          $message          = 'Transaction rejected successfully';
          break;

        default:
          $transaction->status  =  0;
          $transaction->save();
          break;
      endswitch;
    endif;

    $arr      = ['status' => true, 'message' => $message];

    return response()->json($arr);
  }
  # End  Update Withdrawal Status

  /**
   * Admin: fetch game result/status via LK Game API GET /game-status/{gameId} (x-api-key).
   * Stored value may be game_id (24-char hex) or room code (resolved via GET /game-checkroom/{roomCode}).
   *
   * Config: LK_GAME_BASE_URL, LK_GAME_API_KEY
   */
  public function view_ludo_king_result()
  {
    $ludo_king_game_id = request()->ludo_king_game_id;

    if (! $ludo_king_game_id) {
      return response()->json(['status' => false, 'message' => 'Game id required']);
    }

    /*
    |--------------------------------------------------------------------------
    | OLD APIs (deprecated — do not use)
    |--------------------------------------------------------------------------
    | RapidAPI:
    |   GET https://ludo-king-room-code-api.p.rapidapi.com/game-status/{gameId}
    |   Headers: x-rapidapi-host, x-rapidapi-key
    |
    | apihubs.in (LUDO_KING_BASE_URL / LUDO_KING_API_KEY in .env):
    |   Legacy third-party status endpoint — replaced by LK Game API below.
    |--------------------------------------------------------------------------
    */

    $lk = app(LkGameApiService::class);

    if ($lk->apiKey() === '' || $lk->baseUrl() === '') {
      return response()->json([
        'status' => false,
        'message' => 'LK Game API is not configured (set LK_GAME_BASE_URL and LK_GAME_API_KEY in .env)',
      ]);
    }

    $gameId = $lk->resolveGameId($ludo_king_game_id);
    if (! $gameId) {
      return response()->json([
        'status' => false,
        'message' => 'Room not found or could not resolve game id',
      ]);
    }

    $raw = $lk->gameStatus($gameId);
    if (! $raw) {
      return response()->json(['status' => false, 'message' => 'Unable to fetch game status']);
    }

    if (isset($raw->status) && (int) $raw->status === 400) {
      return response()->json([
        'status' => false,
        'message' => $raw->msg ?? 'Game not found',
      ]);
    }

    if (! isset($raw->game_id) && isset($raw->msg)) {
      return response()->json([
        'status' => false,
        'message' => $raw->msg ?? 'Game not found',
      ]);
    }

    $result_data = $lk->normalizeForChallenge($raw);
    $game_status = $result_data->game_status ?? '';

    $view = '';
    $winner_status = '';

    if ($game_status === 'Finished' || $game_status === 'Destroyed') {
      GameChallenge::where('ludo_king_game_id', $ludo_king_game_id)->update([
        'ludo_king_result_details' => json_encode($result_data),
      ]);

      $game_challenge = GameChallenge::where('ludo_king_game_id', $ludo_king_game_id)->first();

      if ($game_challenge && isset($result_data->winner_id, $result_data->creator_id, $result_data->player_id)) {
        if ((string) $result_data->winner_id === (string) $result_data->creator_id) {
          $challenger_name = $game_challenge->challenger->name ?? 'N/A';
          $winner_status = "Challenger ( {$challenger_name} ) is Winner";
        } elseif ((string) $result_data->winner_id === (string) $result_data->player_id) {
          $opponent_name = $game_challenge->opponent->name ?? 'N/A';
          $winner_status = "Opponent ( {$opponent_name} ) is Winner";
        }
      }
    }

    $creatorName = $result_data->creator_name ?? '';
    $playerName = $result_data->player_name ?? '';
    $gameMode = $result_data->game_mode ?? '';

    $view .= "<div><b>Game Status : {$game_status}</b></div>";
    if ($gameMode !== '') {
      $view .= "<div>Mode : {$gameMode}</div>";
    }
    if ($creatorName !== '' || $playerName !== '') {
      $view .= "<div>Creator : {$creatorName} | Player : {$playerName}</div>";
    }
    $view .= "<div><b class='text-success'>{$winner_status}</b></div>";

    return response()->json([
      'status' => true,
      'message' => 'Result Found',
      'view' => $view,
      'data' => $result_data,
    ]);
  }

  
  public function contact_enquires()
  {
    $records = collect([]);

    if (Schema::hasTable('contact_enquires')) {
      try {
        $records = DB::table('contact_enquires')->latest('id')->get();
      } catch (\Throwable) {
        //
      }
    }

    return view('admin.pages.contact-enquires', compact('records'));
  }

  public function remove_gallery_image(Request $request)
  {
    return response()->json(['status' => true, 'message' => 'OK']);
  }

  public function win_to_game_cashbacks(){
    $data = TransferCashback::query()->with('user')->latest() ;

    if(request()->date):
      $data->whereDate('created_at', request()->date);
  endif;
  
    if (request()->ajax()) :
        return datatables()->of($data)
        ->addIndexColumn()
        ->rawColumns(['user.user_details'])
        ->make(true);
    endif;

    return view('admin.pages.wallet.win-to-game-cashbacks');
  }
}
