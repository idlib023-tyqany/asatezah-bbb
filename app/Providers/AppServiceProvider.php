<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
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
        // إلغاء الـ Wrapping التلقائي للاستجابات لـ API نظيف ومباشر
        JsonResource::withoutWrapping();

        // تفعيل الـ Strict Mode في بيئات التطوير فقط للوقاية من N+1 و Mass Assignment
        Model::shouldBeStrict(! app()->environment('production'));

        // تكوين الـ Rate Limiter لحماية مسارات الـ API
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // معدل محدد ومشدد لمسارات الرفع والمصادقة
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perMinute(10)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    }
}
