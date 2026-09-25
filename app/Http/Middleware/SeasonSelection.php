<?php
namespace App\Http\Middleware;
use Closure;
class SeasonSelection
{
    public function handle($request, Closure $next)
    {
        $response=$next($request);
        if (in_array($request->query('type'),['h2h','total','all','none'],true)) {
            $response->headers->setCookie(cookie('ecfhl-season-type',$request->query('type'),525600,'/',null,null,false,false,'lax'));
        }
        return $response;
    }
}
