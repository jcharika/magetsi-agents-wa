<?php

namespace App\Providers;

use App\Services\CustomerFlow\Actions\AirtimeAction;
use App\Services\CustomerFlow\Actions\BillerAction;
use App\Services\CustomerFlow\Actions\BundleAction;
use App\Services\CustomerFlow\Actions\HomeAction;
use App\Services\CustomerFlow\Actions\SupportAction;
use App\Services\CustomerFlow\Actions\TelOneAction;
use App\Services\CustomerFlow\Actions\ZesaAction;
use App\Services\CustomerFlow\Actions\ZesaCalculatorAction;
use App\Services\CustomerFlow\CustomerFlowDispatcher;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->configureHttps();

        $this->app->singleton(CustomerFlowDispatcher::class, function ($app) {
            $dispatcher = $app->make(CustomerFlowDispatcher::class);

            foreach ([
                HomeAction::class,
                ZesaAction::class,
                AirtimeAction::class,
                BundleAction::class,
                TelOneAction::class,
                BillerAction::class,
                ZesaCalculatorAction::class,
                SupportAction::class,
            ] as $actionClass) {
                $dispatcher->registerAction($app->make($actionClass));
            }

            return $dispatcher;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    private function configureHttps(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        URL::forceHttps();
    }
}
