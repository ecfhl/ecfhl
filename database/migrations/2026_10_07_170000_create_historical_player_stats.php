<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $data=json_decode(gzdecode(base64_decode(file_get_contents(database_path('data/player_stats_2025_26.json.gz.b64')),true)),true,512,JSON_THROW_ON_ERROR);
        if ($data['season_id']!=='2025-26' || $data['league_id']!=='vxqmljf1ma1ct9af' || count($data['rows'])<7000 || count(array_unique(array_column($data['rows'],'player_id')))!==count($data['rows'])) throw new RuntimeException('Invalid historical player stats archive.');
        Schema::create('historical_player_stats',function (Blueprint $table) {
            $table->string('season_id');
            $table->string('player_id');
            $table->decimal('fpts',14,6);
            $table->unsignedInteger('gp');
            $table->decimal('fpts_per_game',14,6);
            $table->json('stats_json');
            $table->primary(['season_id','player_id']);
        });
        DB::transaction(function () use ($data) {
            foreach (array_chunk($data['rows'],100) as $batch) DB::table('historical_player_stats')->insert(array_map(fn($row)=>[
                'season_id'=>$data['season_id'],'player_id'=>$row['player_id'],'fpts'=>$row['fpts'],'gp'=>$row['gp'],
                'fpts_per_game'=>$row['rate'],'stats_json'=>json_encode($row['stats'],JSON_THROW_ON_ERROR),
            ],$batch));
        });
    }
    public function down(): void { Schema::dropIfExists('historical_player_stats'); }
};
