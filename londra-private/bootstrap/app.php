<?php
declare(strict_types=1);
use App\Http\Middleware\EnglishAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetPublicLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\VerifyOriginHost::class);
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['locale.public' => SetPublicLocale::class, 'admin.english' => EnglishAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception, \Illuminate\Http\Request $request) {
            if ($exception->getStatusCode() !== 419 || !$request->is('en/*', 'it/*', 'es/*')) { return null; }
            $locale = explode('/', $request->path())[0];
            app()->setLocale($locale);
            $key = $request->is('*/privacy-requests') ? 'privacy_form_expired' : 'form_expired';
            $copy=app(\App\Services\PublicCopy::class)->recovery($locale);
            if(!$request->expectsJson()){
                return response()->view('errors.419',['recovery_url'=>app(\App\Services\PublicFormRecovery::class)->url($request,$locale)],419,$exception->getHeaders())->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow, noarchive');
            }
            return response()->json(['message' => $copy[$key], 'code' => 'form_expired'], 419)->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        });
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $exception, \Illuminate\Http\Request $request) {
            if (!$request->expectsJson() || !$request->is('en/*', 'it/*', 'es/*')) { return null; }
            $locale = explode('/', $request->path())[0];
            $copy=app(\App\Services\PublicCopy::class)->recovery($locale);
            return response()->json(['message' => $copy['retry_after'], 'code' => 'rate_limited'], 429, $exception->getHeaders())->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
        });
    })
    ->create();
$publicSibling = dirname(__DIR__, 2).'/public_html';
if (is_dir($publicSibling) && !is_dir(dirname(__DIR__).'/public/build')) {
    $app->usePublicPath(realpath($publicSibling));
}
return $app;
