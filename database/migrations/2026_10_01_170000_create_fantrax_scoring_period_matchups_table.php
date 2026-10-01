<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fantrax_scoring_period_matchups', function (Blueprint $table) {
            $table->id();
            $table->string('season_id',20)->default('2026-27');
            $table->unsignedSmallInteger('period_number');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('away_team_name');
            $table->decimal('away_score',10,2)->default(0);
            $table->string('home_team_name');
            $table->decimal('home_score',10,2)->default(0);
            $table->timestamps();
            $table->unique(['season_id','period_number','away_team_name','home_team_name'],'fantrax_period_matchup_unique');
            $table->index(['season_id','period_number']);
        });

        $path=database_path('data/2026-27-fantrax-matchups.csv');
        if(!is_file($path))return;

        $handle=fopen($path,'r');
        if(!$handle)return;

        $period=null;
        $rows=[];
        $now=now();

        while(($record=fgetcsv($handle))!==false){
            if(!$record || !isset($record[0]))continue;
            $first=trim((string)$record[0]);

            if(count($record)===1 && preg_match('/^Scoring Period\s+(\d+)$/i',$first,$m)){
                $period=(int)$m[1];
                continue;
            }

            if($period===null || strcasecmp($first,'Away')===0 || count($record)<4)continue;

            $rows[]=[
                'season_id'=>'2026-27',
                'period_number'=>$period,
                'away_team_name'=>(string)$record[0],
                'away_score'=>(float)$record[1],
                'home_team_name'=>(string)$record[2],
                'home_score'=>(float)$record[3],
                'created_at'=>$now,
                'updated_at'=>$now,
            ];
        }
        fclose($handle);

        foreach(array_chunk($rows,100) as $chunk){
            DB::table('fantrax_scoring_period_matchups')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fantrax_scoring_period_matchups');
    }
};
