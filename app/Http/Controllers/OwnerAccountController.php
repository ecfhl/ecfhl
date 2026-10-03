<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Support\OwnerTeams;
use App\Support\OwnerNotificationPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
class OwnerAccountController {
 public function registerForm(Request $r,OwnerTeams $teams){
  if($r->user())return redirect('/account');
  return view('account.register',['teams'=>$teams->all(),'invited'=>$this->invited($r),'googleReady'=>$this->googleReady()]);
 }
 public function register(Request $r,OwnerTeams $teams){
  $v=$r->validate(['name'=>'required|string|max:100','email'=>'required|email|max:255','password'=>'required|string|min:10|max:128|confirmed','team_id'=>'required|string|max:64']);
  $v['email']=strtolower(trim($v['email']));
  if(User::where('email',$v['email'])->exists())throw ValidationException::withMessages(['email'=>'An account already exists for this email. Sign in instead.']);
  try{$user=DB::transaction(function()use($v,$r,$teams){
   $user=User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>$v['password']]);
   $teams->claim($user,$v['team_id'],$this->invited($r));return $user;
  });}catch(\Illuminate\Database\QueryException $e){
   if(in_array((string)$e->getCode(),['23000','23505']))throw ValidationException::withMessages(['team_id'=>'That team or email was just claimed. Please refresh and try again.']);throw $e;
  }
  $this->revokePreviousDevice($r,$user->id);Auth::login($user);$r->session()->regenerate();$r->session()->forget('admin_invited_until');
  return redirect('/notifications')->with('notice','Welcome! Your team is linked to your account.');
 }
 public function login(Request $r){
  $v=$r->validate(['email'=>'required|email','password'=>'required|string']);$v['email']=strtolower(trim($v['email']));
  if(!Auth::attempt($v,$r->boolean('remember')))throw ValidationException::withMessages(['email'=>'Email or password is incorrect.']);
  $this->revokePreviousDevice($r,$r->user()->id);$r->session()->regenerate();return redirect()->intended('/account');
 }
 public function logout(Request $r){
  // Revoke browser delivery on sign-out, without changing the saved account preferences.
  DB::table('push_subscriptions')->where('user_id',$r->user()->id)->where('endpoint_hash',$r->session()->get('push_endpoint_hash',$r->cookie('ecfhl_push_device')))->update(['enabled'=>false,'feed_token_hash'=>null]);
  Auth::logout();$r->session()->invalidate();$r->session()->regenerateToken();return redirect('/');
 }
 public function account(Request $r){return view('account.index',['owner'=>$r->user(),'googleReady'=>$this->googleReady()]);}
 public function password(Request $r){
  $v=$r->validate(['current_password'=>'nullable|string','password'=>'required|string|min:10|max:128|confirmed']);
  if($r->user()->password && !Hash::check($v['current_password']??'', $r->user()->password))throw ValidationException::withMessages(['current_password'=>'Current password is incorrect.']);
  $r->user()->password=$v['password'];$r->user()->remember_token=Str::random(60);$r->user()->save();
  DB::table('sessions')->where('user_id',$r->user()->id)->where('id','!=',$r->session()->getId())->delete();
  return back()->with('notice','Password saved. You can sign in with email and password.');
 }
 public function invite(Request $r){
  $token=(string)config('owners.admin_invite_token');$expires=config('owners.admin_invite_expires');
  abort_unless($token!=='' && $expires && now()->lessThan(\Carbon\Carbon::parse($expires)) && hash_equals($token,(string)$r->query('token')),404);
  abort_if(User::where('is_admin',true)->exists(),410,'The administrator account is already set up.');
  $r->session()->put('admin_invited_until',time()+1800);return redirect('/register')->header('Referrer-Policy','no-referrer');
 }
 private function revokePreviousDevice(Request $r,int $userId): void {
  $hash=$r->cookie('ecfhl_push_device');if(!$hash)return;
  DB::table('push_subscriptions')->where('endpoint_hash',$hash)->where('user_id','!=',$userId)->update(['enabled'=>false,'feed_token_hash'=>null]);
 }
 private function invited(Request $r): bool {return (int)$r->session()->get('admin_invited_until',0)>time() && !User::where('is_admin',true)->exists();}
 public function googleReady(): bool {return (bool)(config('owners.google_client_id')&&config('owners.google_client_secret'));}
 public function google(Request $r){
  if(!$this->googleReady())return redirect('/login')->with('notice','Google sign-in is waiting for league configuration. Email and password are available.');
  $state=Str::random(64);$r->session()->put('google_state',['value'=>$state,'expires'=>time()+600,'user_id'=>$r->user()?->id]);
  return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
   'client_id'=>config('owners.google_client_id'),'redirect_uri'=>config('owners.google_redirect_uri'),
   'response_type'=>'code','scope'=>'openid email profile','state'=>$state,'prompt'=>'select_account',
  ]));
 }
 public function googleCallback(Request $r){
  $state=$r->session()->pull('google_state');
  abort_unless(is_array($state) && ($state['expires']??0)>time() && hash_equals($state['value'],(string)$r->query('state')),419,'Sign-in expired. Please try again.');
  if($r->query('error'))return redirect('/login')->with('notice','Google sign-in was cancelled.');
  abort_unless(is_string($r->query('code')) && $r->query('code')!=='',422);
  try {
   $token=Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token',[
    'code'=>$r->query('code'),'client_id'=>config('owners.google_client_id'),'client_secret'=>config('owners.google_client_secret'),
    'redirect_uri'=>config('owners.google_redirect_uri'),'grant_type'=>'authorization_code',
   ])->throw()->json();
   $profile=Http::withToken($token['access_token'])->timeout(15)->get('https://openidconnect.googleapis.com/v1/userinfo')->throw()->json();
  } catch(\Throwable $e){report($e);return redirect('/login')->withErrors(['email'=>'Google sign-in could not be completed. Please try again.']);}
  abort_unless(($profile['email_verified']??false)===true && !empty($profile['sub']) && filter_var($profile['email']??'',FILTER_VALIDATE_EMAIL),403,'Google must verify your email address.');
  $email=strtolower(trim($profile['email']));$existing=User::where('google_id',$profile['sub'])->first();
  if($state['user_id']){
   abort_unless($r->user() && $r->user()->id===$state['user_id'],403);
   if($existing && $existing->id!==$r->user()->id)throw ValidationException::withMessages(['email'=>'That Google account belongs to another owner account.']);
   if($email!==$r->user()->email)throw ValidationException::withMessages(['email'=>'Choose the Google account with the same email as your owner account.']);
   $r->user()->forceFill(['google_id'=>$profile['sub'],'email_verified_at'=>now()])->save();return redirect('/account')->with('notice','Google connected. Both sign-in methods are available.');
  }
  if(!$existing){
   if(User::where('email',$email)->exists())return redirect('/login')->withErrors(['email'=>'Sign in with your password first, then connect Google in Account.']);
   $existing=User::create(['name'=>mb_substr($profile['name']??$email,0,100),'email'=>$email,'google_id'=>$profile['sub'],'email_verified_at'=>now()]);
  }
  $this->revokePreviousDevice($r,$existing->id);Auth::login($existing);$r->session()->regenerate();return redirect($existing->claim?'/account':'/account/claim-team');
 }
 public function claimForm(Request $r,OwnerTeams $teams){return view('account.claim',['teams'=>$teams->all(),'invited'=>$this->invited($r)]);}
 public function claim(Request $r,OwnerTeams $teams){
  $v=$r->validate(['team_id'=>'required|string|max:64']);
  try{DB::transaction(fn()=>$teams->claim($r->user(),$v['team_id'],$this->invited($r)));}
  catch(\Illuminate\Database\QueryException $e){if(in_array((string)$e->getCode(),['23000','23505']))throw ValidationException::withMessages(['team_id'=>'That team was just claimed. Choose another team.']);throw $e;}
  $r->session()->forget('admin_invited_until');return redirect('/notifications')->with('notice','Team claimed.');
 }
}
