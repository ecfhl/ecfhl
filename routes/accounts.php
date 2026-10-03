<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OwnerAccountController as Accounts;
use App\Http\Controllers\OwnerNotificationsController as Notifications;
Route::get('/login',fn()=>view('account.login',['googleReady'=>app(Accounts::class)->googleReady()]))->middleware('guest')->name('login');
Route::post('/login',[Accounts::class,'login'])->middleware(['guest','throttle:6,1,owner-login']);
Route::get('/register',[Accounts::class,'registerForm'])->name('register');
Route::post('/register',[Accounts::class,'register'])->middleware(['guest','throttle:5,1,owner-write']);
Route::get('/account/admin-invite',[Accounts::class,'invite'])->middleware('throttle:10,1,owner-oauth');
Route::get('/auth/google',[Accounts::class,'google'])->middleware('throttle:10,1,owner-oauth');
Route::get('/auth/google/callback',[Accounts::class,'googleCallback'])->middleware('throttle:20,1,owner-oauth-callback');
Route::middleware('auth')->group(function(){
 Route::post('/logout',[Accounts::class,'logout']);
 Route::get('/account',[Accounts::class,'account']);
 Route::post('/account/password',[Accounts::class,'password'])->middleware('throttle:5,1,owner-write');
 Route::get('/account/claim-team',[Accounts::class,'claimForm']);
 Route::post('/account/claim-team',[Accounts::class,'claim'])->middleware('throttle:5,1,owner-write');
 Route::post('/notifications',[Notifications::class,'save']);
});
Route::get('/notifications',[Notifications::class,'index']);
