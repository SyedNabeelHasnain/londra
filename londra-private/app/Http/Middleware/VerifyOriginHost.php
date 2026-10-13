<?php
declare(strict_types=1);
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
final class VerifyOriginHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if(app()->environment('local','testing')){return $next($request);}
        $expected=strtolower((string)parse_url((string)config('app.url'),PHP_URL_HOST));
        $host=strtolower($request->getHost());
        abort_unless($expected!==''&&in_array($host,[$expected,'www.'.$expected],true),421);
        if($host!==$expected){
            abort_unless(in_array($request->method(),['GET','HEAD'],true),400);
            return redirect(rtrim(config('app.url'),'/').$request->getRequestUri(),308);
        }
        return $next($request);
    }
}
