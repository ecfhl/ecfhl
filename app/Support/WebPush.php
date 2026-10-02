<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebPush
{
    public function publicKey(): string
    {
        [$publicKey] = $this->ensureKeys();
        return $publicKey;
    }

    public function subscribe(string $endpoint): int
    {
        $endpoint=trim($endpoint);
        if($endpoint==='' || !str_starts_with($endpoint,'https://')){
            throw new \InvalidArgumentException('Invalid push subscription endpoint.');
        }

        DB::table('push_subscriptions')->updateOrInsert(
            ['endpoint_hash'=>hash('sha256',$endpoint)],
            [
                'endpoint'=>$endpoint,
                'enabled'=>true,
                'updated_at'=>now(),
                'created_at'=>now(),
            ]
        );

        return (int)(DB::table('push_notifications')->max('id') ?? 0);
    }

    public function unsubscribe(string $endpoint): void
    {
        DB::table('push_subscriptions')
            ->where('endpoint_hash',hash('sha256',trim($endpoint)))
            ->delete();
    }

    public function notify(string $category,string $title,string $body,?string $url=null,?string $fantasyTeamId=null): void
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

        // Keep the anonymous feed compact.
        DB::table('push_notifications')->where('id','<',$id-250)->delete();

        foreach(DB::table('push_subscriptions')->where('enabled',true)->get() as $subscription){
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

        $response=Http::timeout(15)
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
        $public=DB::table('push_settings')->where('setting_key','vapid_public')->value('setting_value');
        $private=DB::table('push_settings')->where('setting_key','vapid_private')->value('setting_value');
        if($public && $private)return [(string)$public,(string)$private];

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

        return [$publicKey,$privatePem];
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
