<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebPush
{
    private ?array $keys = null;
    private function scoreBodyMatches(string $body,string $name): bool
    {
        foreach(PlayerName::searchVariants($name) as $variant){
            if(str_starts_with($body,$variant.' · ') || str_starts_with($body,$variant.' now has '))return true;
        }
        return false;
    }
    public function publicKey(): string
    {
        [$publicKey] = $this->ensureKeys();
        return $publicKey;
    }

    public function subscribe(string $endpoint, int $userId, string $feedToken): int
    {
        $endpoint=trim($endpoint);
        if($endpoint==='' || !str_starts_with($endpoint,'https://')){
            throw new \InvalidArgumentException('Invalid push subscription endpoint.');
        }

        DB::transaction(function () use ($endpoint, $userId, $feedToken) {
            DB::table('push_subscriptions')->updateOrInsert(
                ['endpoint_hash'=>hash('sha256',$endpoint)],
                [
                    'endpoint'=>$endpoint,
                    'enabled'=>true,
                    'user_id'=>$userId,
                    'feed_token_hash'=>hash('sha256',$feedToken),
                    'updated_at'=>now(),
                    'created_at'=>now(),
                ]
            );
            $id=DB::table('push_subscriptions')->where('endpoint_hash',hash('sha256',$endpoint))->value('id');
            // A renewed feed starts now, including when another account uses this device.
            DB::table('push_deliveries')->where('subscription_id',$id)->delete();
        });

        return (int)(DB::table('push_notifications')->max('id') ?? 0);
    }

    public function unsubscribe(string $endpoint): void
    {
        DB::table('push_subscriptions')
            ->where('endpoint_hash',hash('sha256',trim($endpoint)))
            ->delete();
    }

    public function notify(string $category,string $title,string $body,?string $url=null,?string $fantasyTeamId=null,array $context=[]): void
    {
        $id=DB::table('push_notifications')->insertGetId([
            'category'=>$category,
            'title'=>$title,
            'body'=>$body,
            'url'=>$url,
            'fantasy_team_id'=>$fantasyTeamId,
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        // Delivery records cascade when old events are pruned.
        DB::table('push_notifications')->where('id','<',$id-250)->delete();

        $subscriptions=DB::table('push_subscriptions')->where('enabled',true)->whereNotNull('user_id')->whereNotNull('feed_token_hash');
        $owners=\App\Models\User::with('claim')->whereIn('id',$subscriptions->pluck('user_id'))->get()->keyBy('id');
        $policy=new OwnerNotificationPolicy;
        foreach($subscriptions->get() as $subscription){
            $owner=$owners->get($subscription->user_id);
            if(!$owner || !$policy->accepts($owner,$category,$fantasyTeamId,$context))continue;
            DB::table('push_deliveries')->insert(['subscription_id'=>$subscription->id,'notification_id'=>$id]);
            try {
                $status=$this->sendEmptyPush((string)$subscription->endpoint);
                if(in_array($status,[404,410],true)){
                    DB::table('push_subscriptions')->where('id',$subscription->id)->delete();
                } elseif($status>=200 && $status<300){
                    DB::table('push_subscriptions')->where('id',$subscription->id)
                        ->update(['last_push_at'=>now(),'updated_at'=>now()]);
                } else {
                    Log::warning('Browser push returned non-success status',[
                        'subscription_id'=>$subscription->id,
                        'status'=>$status,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Browser push failed',[
                    'subscription_id'=>$subscription->id,
                    'error'=>$e->getMessage(),
                ]);
            }
        }
    }

    public function testLatestScore(int $userId, string $endpointHash): array
    {
        $subscription=DB::table('push_subscriptions')->where('user_id',$userId)->where('endpoint_hash',$endpointHash)->where('enabled',true)->whereNotNull('feed_token_hash')->first();
        if(!$subscription)throw \Illuminate\Validation\ValidationException::withMessages(['device'=>'Enable notifications on this device in Notifications before sending a test.']);
        $score=DB::table('push_notifications')->where('category','live-score')->orderByDesc('id')->first();
        if(!$score)throw \Illuminate\Validation\ValidationException::withMessages(['score'=>'No scoring alert has been recorded yet. Try after the next player earns fantasy points.']);
        $alert=['title'=>$score->title,'body'=>$score->body,'url'=>$score->url,'fantasy_team_id'=>$score->fantasy_team_id];
        // Rebuild older alerts with the same team/stat-line formatter used by live scoring.
        $date=preg_match('/[?&]date=(\d{4}-\d{2}-\d{2})/',(string)$score->url,$match)?$match[1]:(new FantasyDay)->today()->toDateString();
        $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
        foreach(($snapshot['players']??[]) as $player){
            if((string)($player['fantasy_team_id']??'')!==(string)$score->fantasy_team_id)continue;
            if($this->scoreBodyMatches($score->body,$player['player_name'])){
                $alert=\App\Support\LiveScoring\ScoringAlert::payload($snapshot,$player);break;
            }
        }
        if(!str_contains($alert['body'],'G: ')||!str_contains($alert['body'],'GWG: '))throw \Illuminate\Validation\ValidationException::withMessages(['score'=>'The last scoring alert has no game stat line available. Try after the next scoring update.']);
        $id=DB::transaction(function()use($alert,$subscription){
            $id=DB::table('push_notifications')->insertGetId(array_merge($alert,['category'=>'test-score','title'=>$alert['title'].' · TEST','created_at'=>now(),'updated_at'=>now()]));
            DB::table('push_deliveries')->insert(['subscription_id'=>$subscription->id,'notification_id'=>$id]);return $id;
        });
        try{$status=$this->sendEmptyPush($subscription->endpoint);}catch(\Throwable $e){
            DB::table('push_notifications')->where('id',$id)->delete();throw $e;
        }
        if($status<200||$status>=300){
            DB::table('push_notifications')->where('id',$id)->delete();
            if(in_array($status,[404,410],true))DB::table('push_subscriptions')->where('id',$subscription->id)->delete();
            throw \Illuminate\Validation\ValidationException::withMessages(['device'=>'The browser could not receive the test. Re-enable notifications on this device and try again.']);
        }
        DB::table('push_subscriptions')->where('id',$subscription->id)->update(['last_push_at'=>now(),'updated_at'=>now()]);
        return ['ok'=>true,'message'=>'Test sent to this device. '.$alert['title']."\n".$alert['body']];
    }

    public function testType(int $userId,string $endpointHash,string $type): array
    {
        $subscription=DB::table('push_subscriptions')->where('user_id',$userId)->where('endpoint_hash',$endpointHash)->where('enabled',true)->whereNotNull('feed_token_hash')->first();
        if(!$subscription)throw \Illuminate\Validation\ValidationException::withMessages(['device'=>'Enable notifications on this device before sending a test.']);

        $scoreTypes=['team-score','team-goalie-score','opponent-score'];
        $goalieTypes=['own-goalie','all-goalie','available-today','available-tomorrow','watched-goalie'];
        abort_unless(in_array($type,array_merge($scoreTypes,$goalieTypes),true),422,'Unknown notification test.');

        if(in_array($type,$scoreTypes,true)){
            $query=DB::table('push_notifications')->where('category','live-score');
            if($type==='team-goalie-score'){
                // Older goalie alerts used the skater stat line, so identify the
                // player from the saved snapshot instead of relying on body text.
                $source=$query->orderByDesc('id')->get()->first(function($notification){
                    $date=preg_match('/[?&]date=(\\d{4}-\\d{2}-\\d{2})/',(string)$notification->url,$match)?$match[1]:null;
                    if(!$date)return false;
                    $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
                    foreach(($snapshot['players']??[]) as $player){
                        if((string)($player['fantasy_team_id']??'')!==(string)$notification->fantasy_team_id)continue;
                        if(!$this->scoreBodyMatches((string)$notification->body,(string)($player['player_name']??'')))continue;
                        return strtoupper((string)($player['position']??''))==='G';
                    }
                    return false;
                });
            }else{
                // My-team and opponent score tests must use the last skater alert.
                $source=$query->where('body','like','%PPG: %')->where('body','not like','%W: %')->orderByDesc('id')->first();
            }
            $missing='No previous '.($type==='team-goalie-score'?'goalie scoring':'non-goalie scoring').' notification has been sent yet.';
        }else{
            $source=DB::table('push_notifications')->where('category','goalie-status')->orderByDesc('id')->first();
            $missing='No previous goalie status notification has been sent yet.';
        }

        if(!$source)throw \Illuminate\Validation\ValidationException::withMessages(['notification'=>$missing]);

        $url=(string)($source->url??'');
        if(in_array($type,$scoreTypes,true)){
            // Score notifications always open Live Scoring, preserving the fantasy date when available.
            $date=preg_match('/[?&]date=(\d{4}-\d{2}-\d{2})/',$url,$match)?$match[1]:null;
            $url='/teams/current'.($date?'?date='.$date:'');
        }
        // Goalie-status notifications retain their production Fantrax goalie-search URL.
        $alert=[
            'category'=>'test-'.$type,
            'title'=>(string)$source->title.' · TEST',
            'body'=>(string)$source->body,
            'url'=>$url,
            'fantasy_team_id'=>$source->fantasy_team_id,
        ];
        $id=DB::transaction(function()use($alert,$subscription){
            $id=DB::table('push_notifications')->insertGetId(array_merge($alert,['created_at'=>now(),'updated_at'=>now()]));
            DB::table('push_deliveries')->insert(['subscription_id'=>$subscription->id,'notification_id'=>$id]);
            return $id;
        });
        try{$status=$this->sendEmptyPush($subscription->endpoint);}catch(\Throwable $e){DB::table('push_notifications')->where('id',$id)->delete();throw $e;}
        if($status<200||$status>=300){
            DB::table('push_notifications')->where('id',$id)->delete();
            if(in_array($status,[404,410],true))DB::table('push_subscriptions')->where('id',$subscription->id)->delete();
            throw \Illuminate\Validation\ValidationException::withMessages(['device'=>'The browser could not receive the test. Re-enable notifications on this device and try again.']);
        }
        DB::table('push_subscriptions')->where('id',$subscription->id)->update(['last_push_at'=>now(),'updated_at'=>now()]);
        return ['ok'=>true,'message'=>'Last matching notification sent to this device.'];
    }

    private function sendEmptyPush(string $endpoint): int
    {
        [$publicKey,$privatePem]=$this->ensureKeys();
        $parts=parse_url($endpoint);
        if(!$parts || empty($parts['scheme']) || empty($parts['host'])){
            throw new \RuntimeException('Invalid push endpoint.');
        }
        $aud=$parts['scheme'].'://'.$parts['host'];
        if(isset($parts['port']))$aud.=':'.$parts['port'];

        $header=$this->b64(json_encode(['typ'=>'JWT','alg'=>'ES256'],JSON_UNESCAPED_SLASHES));
        $payload=$this->b64(json_encode([
            'aud'=>$aud,
            'exp'=>time()+43200,
            'sub'=>'mailto:notifications@ecfhl.win',
        ],JSON_UNESCAPED_SLASHES));
        $input=$header.'.'.$payload;

        $key=openssl_pkey_get_private($privatePem);
        if(!$key)throw new \RuntimeException('Could not load VAPID private key.');
        if(!openssl_sign($input,$der,$key,OPENSSL_ALGO_SHA256)){
            throw new \RuntimeException('Could not sign VAPID token.');
        }
        $jwt=$input.'.'.$this->b64($this->derToJose($der,64));

        $response=Http::timeout(15)->withoutRedirecting()
            ->withHeaders([
                'Authorization'=>'vapid t='.$jwt.', k='.$publicKey,
                'Crypto-Key'=>'p256ecdsa='.$publicKey,
                'TTL'=>'60',
                'Urgency'=>'normal',
            ])
            ->send('POST',$endpoint,['body'=>'']);

        return $response->status();
    }

    private function ensureKeys(): array
    {
        if ($this->keys !== null) return $this->keys;
        $public=DB::table('push_settings')->where('setting_key','vapid_public')->value('setting_value');
        $private=DB::table('push_settings')->where('setting_key','vapid_private')->value('setting_value');
        if($public && $private)return $this->keys = [(string)$public,(string)$private];

        $resource=openssl_pkey_new([
            'private_key_type'=>OPENSSL_KEYTYPE_EC,
            'curve_name'=>'prime256v1',
        ]);
        if(!$resource)throw new \RuntimeException('Could not generate VAPID key pair.');
        if(!openssl_pkey_export($resource,$privatePem)){
            throw new \RuntimeException('Could not export VAPID private key.');
        }
        $details=openssl_pkey_get_details($resource);
        $x=$details['ec']['x']??null;
        $y=$details['ec']['y']??null;
        if(!is_string($x)||!is_string($y)){
            throw new \RuntimeException('Could not read VAPID public key.');
        }
        $publicKey=$this->b64("\x04".$x.$y);

        DB::transaction(function()use($publicKey,$privatePem){
            DB::table('push_settings')->updateOrInsert(
                ['setting_key'=>'vapid_public'],
                ['setting_value'=>$publicKey,'created_at'=>now(),'updated_at'=>now()]
            );
            DB::table('push_settings')->updateOrInsert(
                ['setting_key'=>'vapid_private'],
                ['setting_value'=>$privatePem,'created_at'=>now(),'updated_at'=>now()]
            );
        });

        return $this->keys = [$publicKey,$privatePem];
    }

    private function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value),'+/','-_'),'=');
    }

    private function derToJose(string $der,int $partLength): string
    {
        $offset=0;
        if(ord($der[$offset++])!==0x30)throw new \RuntimeException('Invalid ECDSA signature.');
        $this->readLength($der,$offset);
        if(ord($der[$offset++])!==0x02)throw new \RuntimeException('Invalid ECDSA signature.');
        $rLen=$this->readLength($der,$offset);
        $r=substr($der,$offset,$rLen);$offset+=$rLen;
        if(ord($der[$offset++])!==0x02)throw new \RuntimeException('Invalid ECDSA signature.');
        $sLen=$this->readLength($der,$offset);
        $s=substr($der,$offset,$sLen);

        $half=intdiv($partLength,2);
        $r=ltrim($r,"\x00");
        $s=ltrim($s,"\x00");
        return str_pad($r,$half,"\x00",STR_PAD_LEFT).str_pad($s,$half,"\x00",STR_PAD_LEFT);
    }

    private function readLength(string $der,int &$offset): int
    {
        $length=ord($der[$offset++]);
        if(($length & 0x80)===0)return $length;
        $count=$length & 0x7f;
        $length=0;
        for($i=0;$i<$count;$i++)$length=($length<<8)|ord($der[$offset++]);
        return $length;
    }
}
