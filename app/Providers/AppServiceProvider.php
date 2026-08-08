<?php

namespace App\Providers;

use App\Http\Resources\NotificationResource;
use App\Models\GameChallenge\GameChallenge;
use App\Models\GameChallenge\Transaction;
use App\Models\GameChallenge\Wallet;
use App\Models\SiteSetting;
use App\Models\User;
use App\Observers\GameChallengeObserver;
use App\Observers\TransactionObserver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use App\Observers\UserObserver;
use App\Observers\WalletObserver;
use Illuminate\Support\Facades\Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        User::observe(UserObserver::class);
        GameChallenge::observe(GameChallengeObserver::class);
        Wallet::observe(WalletObserver::class);
        Transaction::observe(TransactionObserver::class);
        
        #
        Livewire::setScriptRoute(function ($handle) {
            return Route::get('/vendor/livewire/livewire.js', $handle);
        });
        #

        $sitesetting_details            =   SiteSetting::first();

        view()->composer('*', function ($view) {
            $permissions                    =   get_permissions();
            $view->with(['permissions' => $permissions]);
        });

        view()->share('sitesetting_details', $sitesetting_details);
    }
}
