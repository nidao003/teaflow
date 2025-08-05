<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

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
        Schema::defaultStringLength(191);
        if (!env('APP_DEBUG')) {
            error_reporting(0); //关闭所有错误提示
            ini_set('display_errors', 0); //关闭所有错误提示
            ini_set('log_errors', 1); //开启错误日志
            ini_set('error_log', storage_path('logs/laravel.log')); //设置错误日志路径
        }
    }
}
