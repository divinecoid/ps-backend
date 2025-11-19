<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

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
        require_once base_path('app/Libraries/Lazada/Constants.php');
        require_once base_path('app/Libraries/Lazada/UrlConstants.php');
        require_once base_path('app/Libraries/Lazada/LazopLogger.php');
        require_once base_path('app/Libraries/Lazada/LazopClient.php');
        require_once base_path('app/Libraries/Lazada/LazopRequest.php');
        require_once base_path('app/Libraries/Lazada/LazopSdk.php');
    }
}
