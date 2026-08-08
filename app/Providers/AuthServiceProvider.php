<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        //
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        Gate::define('site_settings', function ($user) {
            $user = auth('admin')->user();
            return $user->role_id == 1 ? true : false;
        });
        
        Gate::define('admin', function ($user) {
            $user = auth('admin')->user();
            return $user->role_id == 1 ? true : false;
        });
       
        Gate::define('permissions', function ($user, $module_code, $access_type) {
            $user = auth('admin')->user();

            foreach($user->permissions as $permission):
                if($permission->module_code == $module_code):
                    switch($access_type):
                            case 'create':
                                    return  $permission->rr_create == 1 ? true : false; 
                                break;

                            case 'edit':
                                    return  $permission->rr_edit == 1 ? true : false; 
                                break;

                            case 'view':
                                    return  $permission->rr_view == 1 ? true : false; 
                                break;

                            case 'delete':
                                    return  $permission->rr_delete == 1 ? true : false; 
                                break;
                            
                            default:
                                return false;
                                break;
                    endswitch;
                endif;
            endforeach;

            return false;

        });
    }
}
