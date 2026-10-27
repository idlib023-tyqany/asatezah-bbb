<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Console Commands
|--------------------------------------------------------------------------
*/

// تنظيف المهملات شهرياً (تجنب التداخل والحذف في الخلفية)
Schedule::command('trash:cleanup')
    ->monthly()
    ->withoutOverlapping()
    ->runInBackground();

// تنظيف الرسائل القديمة في الدعم الفني شهرياً
Schedule::command('support:cleanup')
    ->monthly()
    ->withoutOverlapping()
    ->runInBackground();
