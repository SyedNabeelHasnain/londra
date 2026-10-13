<?php
declare(strict_types=1);
namespace App\Providers;
use App\Services\CountryResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CountryResolver::class, \App\Services\LocalCountryResolver::class);
        $this->app->bind(\App\Contracts\EmailTransport::class, \App\Services\ZohoMailTransport::class);
    }
    public function boot(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                $settings = \App\Models\Setting::find('launch-operations')?->value ?? [];
                foreach (['accept_inquiries', 'send_notifications', 'publication_approved', 'privacy_approved', 'indexing_enabled', 'country_lookup'] as $key) {
                    if (array_key_exists($key, $settings)) { config(['londra.'.$key => (bool) $settings[$key]]); }
                }
            }
        } catch (\Throwable) {}
        if (str_starts_with((string) config('app.url'), 'https://')) { URL::forceScheme('https'); }
        RateLimiter::for('inquiries', fn (Request $request) => Limit::perMinute(5)->by(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))));
        RateLimiter::for('form-tokens', fn (Request $request) => Limit::perMinute(5)->by(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))));
        RateLimiter::for('privacy-requests', fn (Request $request) => Limit::perMinute(3)->by(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))));
        \Illuminate\Support\Facades\Session::extend('database', function ($app) {
            return new \App\Services\MinimalDatabaseSessionHandler($app['db']->connection(config('session.connection')), config('session.table'), config('session.lifetime'), $app);
        });
    }
}
