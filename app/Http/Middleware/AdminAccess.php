<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class AdminAccess {
 public function handle(Request $request,Closure $next){
  $protected=$request->is('admin','admin/*','job-status','job-status/*') ||
   ($request->is('team-icons/*','lineup-advisors/*') && !$request->isMethod('GET'));
  if($protected){
   if(!$request->user())return $request->expectsJson()?response()->json(['message'=>'Sign in as an administrator.'],401):redirect()->guest('/login');
   abort_unless($request->user()->is_admin,403,'Administrator access required.');
  }
  $response=$next($request);
  // Authenticated and session-bearing HTML must never be cached by a shared proxy.
  if(str_contains((string)$response->headers->get('Content-Type'),'text/html') || $request->is('push/*','account*','notifications*','auth/*','login','register','admin*')) $response->headers->set('Cache-Control','private, no-store');
  return $response;
 }
}
