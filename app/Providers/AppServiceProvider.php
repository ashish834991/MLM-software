<?php

namespace App\Providers;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\Deposit;
use App\Models\Frontend;
use App\Models\Language;
use App\Models\Page;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Withdrawal;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Fix REQUEST_SCHEME issue on Render
        if (!isset($_SERVER['REQUEST_SCHEME'])) {
            $_SERVER['REQUEST_SCHEME'] = 'https';
        }

        Paginator::useBootstrapFour();

        // Prevent DB access during build/composer commands
        if (app()->runningInConsole()) {
            return;
        }

        try {

            if (
                !\Schema::hasTable('general_settings') ||
                !\Schema::hasTable('languages')
            ) {
                return;
            }

            $general = gs();
            $activeTemplate = activeTemplate();

            $viewShare = [];

            $viewShare['general'] = $general;
            $viewShare['activeTemplate'] = $activeTemplate;
            $viewShare['activeTemplateTrue'] = activeTemplate(true);
            $viewShare['language'] = Language::all();
            $viewShare['emptyMessage'] = 'Data not found';

            $viewShare['pages'] = Page::where('tempname', $activeTemplate)
                ->where('is_default', Status::NO)
                ->get();

            view()->share($viewShare);

            if (!empty($general->force_ssl)) {
                \URL::forceScheme('https');
            }

        } catch (\Exception $e) {

            // Optional logging
            // \Log::error($e->getMessage());

        }
    }
}