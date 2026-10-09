<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use App\Models\TeamClaim;
use App\Models\User;
class OwnerTeams {
 public function all(): \Illuminate\Support\Collection {
  $date=DB::table('active_fantasy_rosters')->max('game_date');
  $rows=DB::table('active_fantasy_rosters')->where('game_date',$date)->select('fantasy_team_id','fantasy_team_name')->distinct()->get();
  // Fresh snapshots also contain every team, even a team with no roster rows.
  $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get((new FantasyDay)->today()->toDateString());
  $teams=collect();
  foreach($rows as $r)$teams->put($r->fantasy_team_id,['id'=>$r->fantasy_team_id,'name'=>$r->fantasy_team_name]);
  foreach(($snapshot['teams']??[]) as $id=>$t)$teams->put($id,['id'=>(string)$id,'name'=>$t['name']]);
  $reserved=DB::table('team_seasons')->where('season_id','2026-27')->where('franchise_id','F012')->value('original_name');
  $claimed=TeamClaim::pluck('user_id','fantasy_team_id');
  return $teams->map(function($t)use($reserved,$claimed){
   $t['slug']=Str::slug($t['name']);$t['claimed']=$claimed->has($t['id']);
   $t['reserved']=$t['name']===$reserved || $t['name']==='Ꮮ૦ท૯⚡️𐌕รคг' || in_array(Str::slug($t['name']),['lone-tsar','lone-star']);
   return $t;
  })->sortBy('name')->values();
 }
 public function claim(User $user,string $id,bool $adminInvite=false): void {
  $team=$this->all()->firstWhere('id',$id);
  if(!$team || $team['claimed'] || ($team['reserved']&&!$adminInvite)) throw ValidationException::withMessages(['team_id'=>'That team is claimed or reserved. Choose another team.']);
  if($user->claim()->exists()) throw ValidationException::withMessages(['team_id'=>'Your account already owns a team.']);
  // The primary key is the final arbiter for simultaneous claims.
  DB::transaction(function()use($user,$id,$team,$adminInvite){
   TeamClaim::create(['fantasy_team_id'=>$id,'user_id'=>$user->id,'team_name'=>$team['name']]);
   if($team['reserved'] && $adminInvite){$user->is_admin=true;$user->save();}
   Messaging::welcomeTeam($team['name']);
  });
 }
}
