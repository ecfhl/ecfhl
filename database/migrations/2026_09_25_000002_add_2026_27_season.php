<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $seasonId = '2026-27';
        $leagueId = '092zcn40molvao69';
        $standings = 'https://www.fantrax.com/fantasy/league/092zcn40molvao69/standings';
        $trades = 'https://www.fantrax.com/fantasy/league/092zcn40molvao69/transactions/history;view=TRADE';

        DB::table('seasons')->updateOrInsert(['season_id'=>$seasonId],[
            'season_name'=>'2026-27','sequence'=>20,'league_id'=>$leagueId,'format'=>'Head-to-Head','cancelled'=>0,
            'status'=>'Upcoming','note'=>'Draft pending; results will be added after the 2026-27 ECFHL draft.',
            'regular_source'=>$standings,'source_id'=>'fantrax-'.$leagueId,
        ]);

        $teams = [
            'BookHockey','Brasse Camarade','Ammon Keys Balls','Mullet Mafia','One Man Bang 💥','North Shore Explorers',
            'Formenton’s Construction Company','Morning Sherwoods','Green Machine','Young Guns','Orcas','Big Bogan Beaking',
            'Ꮮ૦ท૯⚡️𐌕รคг','Multiple Scoregasms'
        ];

        // Resolve each current team through historical team names/aliases, preserving existing franchise identities.
        $franchises = DB::table('franchises')->get();
        $aliases = DB::table('franchise_aliases')->get();
        $history = DB::table('team_seasons')->get();
        $normalize = fn($s)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace(["’","‘"],"'",$s))));
        $lookup=[];
        foreach($franchises as $f)$lookup[$normalize($f->franchise_name)]=$f->franchise_id;
        foreach($aliases as $a)$lookup[$normalize($a->alias_name)]=$a->franchise_id;
        foreach($history as $h)$lookup[$normalize($h->original_name)]=$h->franchise_id;

        foreach($teams as $i=>$name){
            $fid=$lookup[$normalize($name)]??null;
            if(!$fid){
                $fid='f-'.substr(sha1($name),0,12);
                DB::table('franchises')->updateOrInsert(['franchise_id'=>$fid],[
                    'franchise_name'=>$name,'active_in_2025_26'=>0,'source_id'=>'fantrax-'.$leagueId
                ]);
            }
            DB::table('season_members')->updateOrInsert(['season_member_id'=>$seasonId.'-'.$fid],[
                'season_id'=>$seasonId,'franchise_id'=>$fid,'source_id'=>'fantrax-'.$leagueId
            ]);
            DB::table('team_seasons')->updateOrInsert(['team_season_id'=>$seasonId.'-'.$fid],[
                'season_id'=>$seasonId,'franchise_id'=>$fid,'original_name'=>$name,
                'rank'=>null,'w'=>0,'l'=>0,'t'=>0,'standings_points'=>null,'fantasy_points_for'=>null,'fantasy_points_against'=>null,
                'player_games'=>null,'fantasy_points_per_player_game'=>null,'source'=>$standings,'team_url'=>null,'source_id'=>'fantrax-'.$leagueId
            ]);
        }

        // The draft has not happened yet, so intentionally create no draft/draft_pick rows.
        // Add only the trade whose assets were fully readable from the public Fantrax history.
        $gm=$lookup[$normalize('Green Machine')]??null;
        $ms=$lookup[$normalize('Morning Sherwoods')]??null;
        if($gm && $ms){
            $tid='2026-27-20260923-green-machine-morning-sherwoods';
            DB::table('trades')->updateOrInsert(['trade_id'=>$tid],[
                'season_id'=>$seasonId,'source_trade_id'=>null,'from_franchise_id'=>$gm,'to_franchise_id'=>$ms,
                'from_name_raw'=>'Green Machine','to_name_raw'=>'Morning Sherwoods','trade_datetime'=>'2026-09-23 03:00:00',
                'trade_date_raw'=>'Wed Sep 23, 2026, 3:00 AM','period'=>null,'status'=>'Executed','is_vetoed'=>0,'is_reversed'=>0,
                'executed_explicit'=>1,'objection_count'=>null,'proposer_unknown'=>null,'source'=>$trades,'source_id'=>'fantrax-'.$leagueId
            ]);
            $assets=[
                ['from',$gm,$ms,'player','Matthew Tkachuk'],
                ['from',$gm,$ms,'player','Trevor Zegras'],
                ['from',$gm,$ms,'draft_pick','2027 Draft Pick Round 3 (Morning Sherwoods)'],
                ['to',$ms,$gm,'player','Mitch Marner'],
                ['to',$ms,$gm,'draft_pick','2026 Draft Pick Round 2 Pick 10'],
                ['to',$ms,$gm,'draft_pick','2026 Draft Pick Round 2 Pick 7'],
                ['to',$ms,$gm,'draft_pick','2026 Draft Pick Round 3 Pick 1'],
                ['to',$ms,$gm,'player','Kyle Connor'],
                ['to',$ms,$gm,'draft_pick','2026 Draft Pick Round 5 Pick 13'],
            ];
            foreach($assets as $i=>$a){
                [$side,$from,$to,$type,$desc]=$a;
                preg_match('/(20\d{2}) Draft Pick Round (\d+)/i',$desc,$m);
                DB::table('trade_assets')->updateOrInsert(['trade_asset_id'=>$tid.'-'.($i+1)],[
                    'trade_id'=>$tid,'source_side'=>$side,'item_order'=>$i+1,'from_franchise_id'=>$from,'to_franchise_id'=>$to,
                    'asset_type'=>$type,'asset_description'=>$desc,'player_id'=>null,'draft_year'=>$m[1]??null,'draft_round'=>$m[2]??null,
                    'pick_original_franchise_id'=>null,'pick_original_team_raw'=>null,'contract_years_at_trade'=>null
                ]);
            }
        }
    }

    public function down(): void
    {
        $ids=DB::table('trades')->where('season_id','2026-27')->pluck('trade_id');
        DB::table('trade_assets')->whereIn('trade_id',$ids)->delete();
        DB::table('trades')->where('season_id','2026-27')->delete();
        DB::table('team_seasons')->where('season_id','2026-27')->delete();
        DB::table('season_members')->where('season_id','2026-27')->delete();
        DB::table('seasons')->where('season_id','2026-27')->delete();
    }
};
