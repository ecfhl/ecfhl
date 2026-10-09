<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
class User extends Authenticatable {
 use Notifiable;
 protected $fillable=['name','email','password','google_id','email_verified_at'];
 protected $hidden=['password','remember_token','google_id'];
 protected function casts(): array {return ['is_admin'=>'boolean','password'=>'hashed','email_verified_at'=>'datetime','notification_preferences'=>'array','ui_preferences'=>'array'];}
 public function claim(){return $this->hasOne(TeamClaim::class);}
}
