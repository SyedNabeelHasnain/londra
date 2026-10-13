<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use App\Domain\ReleasePolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', $request->is('*/privacy-request*') ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $private = $request->is('admin*', 'livewire*', '*/form-tokens', '*/privacy-request/verify/*') || !$request->isMethod('GET');
        $indexable = false;
        if (!$private && ReleasePolicy::indexAllowed(config('londra'))) {
            try { $indexable = app(\App\Services\PublicationState::class)->indexingAllowed(); } catch (\Throwable) {}
        }
        if (!$indexable) { $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive'); }
        $response->headers->set('Cache-Control', 'private, no-store');
        if (!$request->is('admin*', 'livewire*')) {
            $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");
        }
        return $response;
    }
}
