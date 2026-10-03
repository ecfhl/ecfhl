<?php

namespace App\Support\LiveScoring;

use App\Support\FantasyDay;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxClient
{
    public const LEAGUE_ID = '092zcn40molvao69';
    // API date semantics were verified independently of the Pacific league-day boundary.
    public const API_TIMEZONE = 'America/Halifax';
    public const URL = 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/livescoring';

    public function matchup(string $date, string $view): array
    {
        (new FantasyDay)->parse($date);
        $data = $this->request('getLiveScoringStats', [
            'newView'=>true, 'date'=>$date, 'viewType'=>$view,
            'playerViewType'=>'2', 'sppId'=>'-1', 'teamId'=>'ALL',
        ]);
        if (($data['date'] ?? null) !== $date || ($data['displayedSelections']['date'] ?? null) !== $date
            || (string)($data['displayedSelections']['viewTypeId'] ?? '') !== $view
            || (string)($data['displayedSelections']['realOrStatProjProvider']['id'] ?? '') !== '-1') {
            throw new RuntimeException('Fantrax returned a different date, timeframe, or scoring provider.');
        }
        return $data;
    }

    public function request(string $method, array $data): array
    {
        $response = Http::timeout(35)->retry(2, 1000)->withHeaders([
            'User-Agent'=>'Mozilla/5.0', 'Accept'=>'application/json', 'Referer'=>self::URL,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID, [
            'msgs'=>[['method'=>$method, 'data'=>$data]], 'uiv'=>3, 'refUrl'=>self::URL,
            'dt'=>0, 'at'=>0, 'av'=>'0.0', 'tz'=>self::API_TIMEZONE, 'v'=>'186.1.9',
        ]);
        $response->throw();
        $json = $response->json();
        if (!is_array($json) || !empty($json['pageError']) || !empty($json['responses'][0]['error'])) {
            throw new RuntimeException('Fantrax request failed: '.$method);
        }
        $result = $json['responses'][0]['data'] ?? null;
        if (!is_array($result) || !empty($result['warming'])) throw new RuntimeException('Fantrax returned no complete snapshot: '.$method);
        unset($result['clientId'], $result['ownTeamIds'], $result['resourceMap']);
        return $result;
    }
}
