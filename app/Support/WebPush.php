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
            if(str_starts_with($body,$variant.' · ') || str_starts_with($body,$variant.' now has ') || str_contains($body,' by '.$variant.' (') || str_ends_with($body,' by '.$variant))return true;
            if(preg_match('/^'.preg_quote($variant,'/').'(?: \([^)]+\))? (?:scores|adds|records|takes|has)\b/u',$body))return true;
        }
        return false;
    }

    private function replayScore(object $source): array
    {
        $alert=['title'=>(string)$source->title,'body'=>(string)$source->body,'url'=>$source->url,'fantasy_team_id'=>$source->fantasy_team_id];
        // New alerts already contain the exact event and totals from the time they were sent.
        if(preg_match('/ - -?\d+(?:\.\d+)?pts$/',$alert['title']) && preg_match('/ (?:scores|adds|records|takes|has)\b/u',$alert['body']))return $alert;
        $date=preg_match('/[?&]date=(\d{4}-\d{2}-\d{2})/',(string)$source->url,$match)?$match[1]:(new FantasyDay)->today()->toDateString();
        $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
        $player=null;
        foreach(($snapshot['players']??[]) as $candidate){
            if((string)($candidate['fantasy_team_id']??'')===(string)$source->fantasy_team_id && $this->scoreBodyMatches($alert['body'],(string)($candidate['player_name']??''))){$player=$candidate;break;}
        }
        if(!$player && preg_match('/^(.+?) · (-?\d+(?:\.\d+)?) FPts\b/u',$alert['body'],$match)){
            // Old labelled records can still be reformatted after their snapshot expires.
            $stats=[];
            preg_match_all('/\b(GP|G|A|PPG|SHG|GWG|W|L|OTL|OL\+ShL|OL|SO|SHO):\s*(-?\d+)/',$alert['body'],$values,PREG_SET_ORDER);
            foreach($values as $value)$stats[$value[1]]=['value'=>(int)$value[2]];
            if($stats){
                $team=preg_replace('/^ECFHL\s*[·:-]\s*|\s*· TEST$/u','',$alert['title']);
                $snapshot=['fantasy_date'=>$date,'teams'=>[$source->fantasy_team_id=>['name'=>$team]]];
                $player=['fantasy_team_id'=>$source->fantasy_team_id,'player_name'=>$match[1],'daily_fpts'=>(float)$match[2],
                    'position'=>array_intersect(['W','L','OTL','OL+ShL','OL','SO','SHO'],array_keys($stats))?'G':'F','stats'=>$stats];
            }
        }
        if(!$player)throw \Illuminate\Validation\ValidationException::withMessages(['score'=>'The last scoring alert has no game stat line available. Try after the next scoring update.']);
        $previous=null;
        if(str_contains($alert['body'],' by ')){
            // Preserve the recorded event, rather than announcing all earlier goals again.
            $event=explode(' by ',$alert['body'],2)[0];
            $previous=$player;
            foreach(['G'=>'Goals?','A'=>'Assists?','GWG'=>'GWG','PPG'=>'PPG|Power-play Goal','SHG'=>'SHG|Short-handed Goal','W'=>'Win','L'=>'Loss','OTL'=>'Overtime Loss','SO'=>'Shutout','SHO'=>'Shutout'] as $key=>$pattern){
                if(!isset($previous['stats'][$key]))continue;
                $delta=preg_match('/\b(?:(\d+) )?(?:'.$pattern.')\b/i',$event,$match)?(int)($match[1]??1):0;
                $previous['stats'][$key]['value']=max(0,(int)$player['stats'][$key]['value']-$delta);
            }
        }
        return \App\Support\LiveScoring\ScoringAlert::payload($snapshot,$player,$previous);
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
        $owners=\App\Models\User::with('claim')->get()->keyBy('id');
        $policy=new OwnerNotificationPolicy;
        if(!in_array($category,['private-message','league-message'],true)){
            $inbox=[];
            foreach($owners as $owner){
                if($policy->accepts($owner,$category,$fantasyTeamId,$context))$inbox[]=['user_id'=>$owner->id,'notification_id'=>$id,'created_at'=>now(),'updated_at'=>now()];
            }
            if($inbox)DB::table('owner_notification_inbox')->insert($inbox);
        }
        foreach($subscriptions->get() as $subscription){
            $owner=$owners->get($subscription->user_id);
            if(!$owner || !(array_replace(OwnerNotificationPolicy::DEFAULTS,$owner->notification_preferences??[])['notifications_enabled']) || !$policy->accepts($owner,$category,$fantasyTeamId,$context))continue;
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
        $alert=$this->replayScore($score);
        $id=DB::transaction(function()use($alert,$subscription){
            $id=DB::table('push_notifications')->insertGetId(array_merge($alert,['category'=>'test-score','created_at'=>now(),'updated_at'=>now()]));
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

    public function testMessage(int $userId,string $endpointHash,string $type): array
    {
        abort_unless(in_array($type,['league','private'],true),422);
        $preferences=Messaging::preferences(\App\Models\User::findOrFail($userId));
        if(!$preferences['notifications_enabled'] || !$preferences[$type==='league'?'league_message_push':'private_message_push'])
            throw \Illuminate\Validation\ValidationException::withMessages(['preferences'=>'Turn on the matching message notifications first.']);
        $subscription=DB::table('push_subscriptions')->where('user_id',$userId)->where('endpoint_hash',$endpointHash)->where('enabled',true)->whereNotNull('feed_token_hash')->first();
        if(!$subscription)throw \Illuminate\Validation\ValidationException::withMessages(['device'=>'Enable notifications on this device before sending a test.']);
        $id=DB::transaction(function()use($subscription,$type){
            $id=DB::table('push_notifications')->insertGetId(['category'=>'test-'.$type.'-message','title'=>($type==='league'?'League chat':'Private message').' · TEST','body'=>'This is a message notification test. No message was sent to another person.','url'=>'/messages','created_at'=>now(),'updated_at'=>now()]);
            DB::table('push_deliveries')->insert(['subscription_id'=>$subscription->id,'notification_id'=>$id]);return $id;
        });
        try{$status=$this->sendEmptyPush($subscription->endpoint);if($status<200||$status>=300)throw new \RuntimeException('The browser could not receive the test. Re-enable notifications on this device and try again.');}
        catch(\Throwable $error){DB::table('push_notifications')->where('id',$id)->delete();throw \Illuminate\Validation\ValidationException::withMessages(['device'=>$error->getMessage()]);}
        return ['ok'=>true,'message'=>'Test message notification sent to this device.'];
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
            // Identify the player from the dated snapshot; message wording is presentation only.
            $wantGoalie=$type==='team-goalie-score';
            $source=$query->orderByDesc('id')->get()->first(function($notification)use($wantGoalie){
                $date=preg_match('/[?&]date=(\d{4}-\d{2}-\d{2})/',(string)$notification->url,$match)?$match[1]:null;
                $snapshot=$date?app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date):null;
                foreach(($snapshot['players']??[]) as $player){
                    if((string)($player['fantasy_team_id']??'')!==(string)$notification->fantasy_team_id)continue;
                    if(!$this->scoreBodyMatches((string)$notification->body,(string)($player['player_name']??'')))continue;
                    $position=$player['position']??'';
                    if(is_array($position))$position=implode(',',$position);
                    return (bool)preg_match('/(^|[,\/ ])G($|[,\/ ])/i',(string)$position)===$wantGoalie;
                }
                // Preserve replay for legacy records whose dated snapshot is no longer available.
                $body=(string)$notification->body;
                $goalie=(bool)preg_match('/\b(?:W|L|OTL|SO|SHO): | (?:records (?:a win|a shutout)|takes (?:a loss|an overtime loss))\b/',$body);
                return $goalie===$wantGoalie && ($goalie || preg_match('/\b(?:G|A|PPG|SHG|GWG): /',$body));
            });
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
        if(in_array($type,$scoreTypes,true))$alert=array_merge($alert,$this->replayScore($source),['url'=>$url]);
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
