<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GameChallenge\CommissionController;
use App\Http\Controllers\Admin\GameChallenge\GameChallengeController;
use App\Http\Controllers\Admin\GameChallenge\TransactionController;
use App\Http\Controllers\Admin\GameChallenge\WalletController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\Management\MemberController;
use App\Http\Controllers\Admin\SiteSettingController;
use App\Http\Controllers\Admin\SliderController;
use App\Http\Controllers\Admin\StandardExampleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\CustomAuth\AdminAuthController;

use App\Http\Controllers\Admin\Site\Media\CategoryController as MediaCategoryController;
use App\Http\Controllers\Admin\Site\Media\MediaController;
use App\Http\Controllers\Admin\Site\Page\PageController;
use App\Http\Controllers\Admin\Notification\NotificationController;
use App\Http\Controllers\Admin\StorageManagerController;
use App\Models\GameChallenge\CommissionHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

// Livewire update endpoint (under /admin prefix from RouteServiceProvider).
Livewire::setUpdateRoute(function ($handle) {
    return Route::post('/livewire/update', $handle);
});

Route::get('refresh', function () {
    event(new App\Events\DemoEvent('John'));
    return "Event has been sent!";
});

Route::get('login', [AdminAuthController::class, 'index']);

Route::post('/login-process', [AdminAuthController::class, 'login_process']);
# End Admin

Route::group(['as' => 'admin::', 'middleware' => 'auth:admin'], function () {
	Route::get("/", [AdminController::class, 'index'])->name('dashboard');
	
	# Credentials
	Route::get("validate-upi", function(){
       return HaodaPay()->validateUpi();
	});
	# End Credentials

	# change Password
	Route::post('change-password', [AdminAuthController::class, 'change_password']);
	# End change Password

	# Resources
	Route::resources([
		'roles' 											=>  RoleController::class,
		'modules' 											=>  ModuleController::class,
		'members' 											=>  MemberController::class,
		'categories' 										=>  CategoryController::class,
		'sitesettings' 										=>  SiteSettingController::class,
		'sliders' 											=>  SliderController::class,
		'users' 											=>  UserController::class,
		'standard-demo' 									=>  StandardExampleController::class,

		'notifications'                               		=> NotificationController::class,

		'game-challenges'                               	=> GameChallengeController::class,
		'wallet-transactions'                               => WalletController::class,
		'transactions'                               		=> TransactionController::class,
		
		# Site
		'site-media-categories'								=> MediaCategoryController::class,
		'site-media'										=> MediaController::class,
		'pages'												=> PageController::class,
		# Site
	]);
	# End Resources

	# Restore
	// Route::get('restore-course-type/{id}', [CourseTypeController::class, 'restore'])->name('course-types.restore');
	# End Restore

	# View
	// Route::get('get-course-category-options', [AdminController::class, 'get_course_category_options']);
	# End View

	Route::post("update-transaction-status", [AdminController::class, 'update_transaction_status']);
	Route::get("contact-enquires", [AdminController::class, 'contact_enquires']);

	Route::get("users-sponsor-search", [UserController::class, 'searchSponsors']);
	Route::get("report", [UserController::class, 'index']);

	Route::get("game-credit-and-debit", [WalletController::class, 'index']);
	Route::get("win-credit-and-debit", [WalletController::class, 'index']);
	Route::get("game-ledger", [WalletController::class, 'index']);

	Route::post("wallet-transaction-process", [WalletController::class, 'wallet_transaction_process']);
	Route::post("update-game-challenge-result", [GameChallengeController::class, 'update_game_challenge_result']);
	Route::post("delete-game-challenge", [GameChallengeController::class, 'delete_game_challenge']);

	# King (Daddy King) WebSocket sync monitor
	Route::get("king-sync", [App\Http\Controllers\Admin\KingController::class, 'index']);
	Route::post("king-sync/retry-outbox", [App\Http\Controllers\Admin\KingController::class, 'retryOutbox']);
	Route::post("king-sync/toggle-pause", [App\Http\Controllers\Admin\KingController::class, 'togglePause']);
	
	# Commissions
	Route::get("refer-commissions", [ CommissionController::class, 'index' ]);
	Route::get("game-commissions", [ CommissionController::class, 'index' ]);
	Route::get("game-commission-slot", [ CommissionController::class, 'game_commission_slot' ]);
	Route::get("user-commissions", [ CommissionController::class, 'user_commissions' ]);
	Route::get("fetch-user-details", [ CommissionController::class, 'fetch_user_details' ]);
	Route::post("update-user-commission", [ CommissionController::class, 'update_user_commission' ]);
	Route::get("user-commissions-list", [ CommissionController::class, 'user_commissions_list' ]);
	Route::post("update-game-commission-slot", [ CommissionController::class, 'update_game_commission_slot' ]);
	
	# End Commissions

	Route::get("role-permission/{id}", [RoleController::class, 'role_permission'])->name('role_permission');

	Route::get("sliders/{id}/slides", [SliderController::class, 'slides'])->name('slides');
	Route::get("sliders/{id}/add_slide", [SliderController::class, 'add_slide'])->name('add_slide');
	Route::get("sliders/{id}/edit-slide/{slide_id}", [SliderController::class, 'edit_slide'])->name('edit_slide');
	Route::post("sliders/add_slide_process", [SliderController::class, 'add_slide_process'])->name('add_slide_process');
	Route::post("sliders/edit_slide_process", [SliderController::class, 'edit_slide_process'])->name('edit_slide_process');
	Route::delete("sliders/{id}/deleteSlide", [SliderController::class, 'deleteSlide'])->name('sliders.deleteSlide');

	Route::post("role-permission-update", [RoleController::class, 'role_permission_update'])->name('role_permission_update');


	Route::post("logout", [AdminAuthController::class, 'logout'])->name('logout');

	Route::get("sliders/{id}/slides", [SliderController::class, 'slides'])->name('slides');
	Route::get("sliders/{id}/add_slide", [SliderController::class, 'add_slide'])->name('add_slide');
	Route::post("sliders/add_slide_process", [SliderController::class, 'add_slide_process'])->name('add_slide_process');
	Route::get("sliders/{id}/edit-slide/{id2}", [SliderController::class, 'edit_slide'])->name('edit_slide');
	Route::post("sliders/edit_slide_process", [SliderController::class, 'edit_slide_process'])->name('edit_slide_process');
	Route::delete("sliders/deleteSlide/{id}", [SliderController::class, 'deleteSlide'])->name('sliders.deleteSlide');
	Route::post("slides/updateSortOrder", [SliderController::class, 'updateSortOrder']);

	Route::post("remove-gallery-image", [AdminController::class, 'remove_gallery_image']);

	Route::get("view-ludo-king-result", [AdminController::class, 'view_ludo_king_result']);
	Route::get("win-to-game-cashbacks", [WalletController::class, 'win_to_game_cashbacks']);

	Route::get('/storage-folders-and-files/{any?}', [StorageManagerController::class, 'index'])->where('any', '.*');

	Route::get('optimize-space', function () {
		$directories = Storage::disk('public')->directories('proof'); // Get all main subfolders inside 'proof'
	
		foreach ($directories as $directory) {
			$files = Storage::disk('public')->allFiles($directory); // Get all files inside subfolder
	
			foreach ($files as $file) {
				$path = storage_path("app/public/{$file}"); // Full path to file
				
				if (File::exists($path)) {
					$modifiedTime = File::lastModified($path);
					$ageInMonths = Carbon::createFromTimestamp($modifiedTime)->diffInMonths(Carbon::now());
	
					if ($ageInMonths >= 2) {
						Storage::disk('public')->delete($file); // Delete file
					}
				}
			}
			// Check if folder is now empty & delete it
			if (empty(Storage::disk('public')->allFiles($directory))) {
				Storage::disk('public')->deleteDirectory($directory);
			}
		}

		return back()->with('back_msg', "Old files deleted successfully!");
		// return response()->json(['message' => 'Old files deleted successfully!']);
	});
});
