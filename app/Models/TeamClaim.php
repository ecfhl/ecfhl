<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TeamClaim extends Model {
 protected $table='owner_team_claims'; protected $primaryKey='fantasy_team_id'; public $incrementing=false; protected $keyType='string';
 protected $fillable=['fantasy_team_id','user_id','team_name'];
}
