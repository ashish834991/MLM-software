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
        Paginator::useBootstrapFour();

        try {

            if (!\Schema::hasTable('general_settings') || !\Schema::hasTable('languages')) {
                return;
            }

            $general = gs();
            $activeTemplate = activeTemplate();

            $viewShare['general'] = $general;
            $viewShare['activeTemplate'] = $activeTemplate;
            $viewShare['activeTemplateTrue'] = activeTemplate(true);
            $viewShare['language'] = Language::all();
            $viewShare['emptyMessage'] = 'Data not found';

            $viewShare['pages'] = Page::where('tempname', $activeTemplate)
                ->where('is_default', Status::NO)
                ->get();

            view()->share($viewShare);

            if ($general->force_ssl) {
                \URL::forceScheme('https');
            }

        } catch (\Exception $e) {

        }
    }
}