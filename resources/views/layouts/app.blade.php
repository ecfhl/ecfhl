<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        if (request()->is('/')) {$browserTitle='East Coast Fantasy Hockey League';}
        elseif(request()->is('teams/current/*')&&isset($teamName)){$browserTitle='ECFHL - '.$teamName;}
        elseif(request()->is('teams/*')&&isset($team)){$browserTitle='ECFHL - '.($team['team']??'Franchise');}
        elseif(request()->is('seasons/*')&&isset($season)){$browserTitle='ECFHL - '.($season['season']??'Season');}
        elseif(request()->is('teams/current')){$browserTitle='ECFHL - Live Scoring';}
        else{$pageTitles=['seasons'=>'Seasons','standings'=>'Standings','teams'=>'Franchises','prizes'=>'Prizes','trades'=>'Trades','draft'=>'Draft','players'=>'Players','daily-targets'=>'Daily Targets','job-status'=>'Collector Status','rules'=>'Rules'];$browserTitle='ECFHL - '.($pageTitles[request()->segment(1)]??'East Coast Fantasy Hockey League');}
        $showSeasonFilter=!request()->is('rules','players','daily-targets','job-status','teams/current','teams/current/*','seasons','seasons/*');if($showSeasonFilter)$seasonMode=app(\App\Support\Archive::class)->mode();
        $currentTeamMenu=\Illuminate\Support\Facades\DB::table('team_seasons as ts')
            ->join('seasons as s','s.season_id','=','ts.season_id')
            ->where('s.season_name','2026-27')
            ->orderBy('ts.original_name')
            ->pluck('ts.original_name')
            ->all();
    @endphp
    <title>{{ $browserTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=7"><link rel="shortcut icon" href="/favicon.svg?v=7"><link rel="apple-touch-icon" href="/ecfhl-logo.png?v=7"><link rel="stylesheet" href="/app.css?v=6"><link rel="stylesheet" href="/header-filters.css?v=3">
<style>
html,body,main{max-width:100%;overflow-x:clip}.push-notification-toggle{border:0;background:transparent;color:inherit;font-size:18px;line-height:1;cursor:pointer;padding:7px;border-radius:8px}.push-notification-toggle:hover{background:var(--surface-2,rgba(255,255,255,.08))}.push-notification-toggle.push-enabled{background:#dcfce7;color:#166534}.push-notification-toggle.push-blocked{opacity:.5}
.nav-dropdown{position:relative;display:flex;align-items:center}.nav-dropdown-row{display:flex;align-items:center}.nav-dropdown-main-link{display:block}.nav-dropdown-toggle{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:inherit;padding:8px 7px;cursor:pointer;border-radius:7px}.nav-dropdown.active>.nav-dropdown-row,.nav-dropdown-toggle:hover{background:var(--surface-2,rgba(255,255,255,.08));border-radius:7px}.nav-dropdown-menu{display:none;position:absolute;top:100%;left:0;z-index:1000;min-width:430px;grid-template-columns:repeat(2,minmax(190px,1fr));gap:2px;padding:8px;background:#082f4f;border:1px solid #6b88a0;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.28);color:#fff}.nav-dropdown:hover .nav-dropdown-menu,.nav-dropdown.open .nav-dropdown-menu{display:grid}.nav-dropdown-menu a{display:block;padding:8px 10px!important;border-radius:7px;white-space:nowrap;text-decoration:none;color:#fff!important}.nav-dropdown-menu a:hover{background:#12486f}.archive-menu{min-width:180px!important;grid-template-columns:1fr!important}.nav-dropdown-menu .nav-all-teams-link,.nav-dropdown-menu .nav-history-link{grid-column:1/-1;font-weight:800}.nav-dropdown-menu .nav-all-teams-link{border-bottom:1px solid #6b88a0;margin-bottom:4px}.nav-dropdown-menu .nav-history-link{border-top:1px solid #6b88a0;margin-top:4px;padding-top:9px!important}
@media(max-width:900px){.nav-dropdown{display:block;width:100%}.nav-dropdown-row{display:grid;grid-template-columns:1fr auto;align-items:center;width:100%}.nav-dropdown-main-link{text-align:center!important;padding:10px 0!important}.nav-dropdown-toggle{width:44px;text-align:center;padding:10px 0}.nav-dropdown:hover .nav-dropdown-menu{display:none}.nav-dropdown.open .nav-dropdown-menu{display:grid!important;position:static;min-width:0;width:100%;grid-template-columns:1fr;background:transparent;border:0;box-shadow:none;padding:4px 0 8px 12px}.nav-dropdown-menu a{padding:8px 0!important;text-align:center!important}.nav-dropdown-menu .nav-history-link{border-top:1px solid var(--line);padding-top:10px!important}}
</style>
</head>
<body>
<header class="site-header"><div class="shell nav-wrap"><div class="brand-area"><a class="brand" href="/"><img class="brand-logo" src="{{ asset('ecfhl-logo.png') }}" alt="ECFHL league logo"><span class="brand-copy"><strong>EAST COAST</strong><small>FANTASY HOCKEY LEAGUE</small></span></a></div><div class="header-actions"><button id="push-notifications-button" class="push-notification-toggle" type="button" aria-label="Enable browser notifications" title="Enable browser notifications">🔔</button><button class="theme-toggle header-theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch theme">◐</button><button class="nav-toggle" type="button" aria-label="Toggle navigation" onclick="document.body.classList.toggle('nav-open')">☰</button></div><nav class="main-nav">
<a href="/" class="{{ request()->is('/')?'active':'' }}">Overview</a>
<a href="/teams/current" class="{{ request()->is('teams/current','teams/current/*')?'active':'' }}">Live Scoring</a>
<a href="/standings" class="{{ request()->is('standings')?'active':'' }}">Standings</a>
<a href="/daily-targets" class="{{ request()->is('daily-targets')?'active':'' }}">Daily Targets</a>
<div class="nav-dropdown {{ request()->is('teams')||request()->is('teams/*')&&!request()->is('teams/current','teams/current/*')?'active':'' }}">
  <div class="nav-dropdown-row">
    <a class="nav-dropdown-main-link" href="/standings">Teams</a>
    <button type="button" class="nav-dropdown-toggle" onclick="this.closest('.nav-dropdown').classList.toggle('open')" aria-label="Open Teams menu" aria-expanded="false"><span aria-hidden="true">▾</span></button>
  </div>
  <div class="nav-dropdown-menu">
    @foreach($currentTeamMenu as $currentTeamName)
      <a href="/teams/current/{{ \Illuminate\Support\Str::slug($currentTeamName) }}">{{ $currentTeamName }}</a>
    @endforeach
    <a class="nav-history-link" href="/teams">Franchise History</a>
  </div>
</div>
<div class="nav-dropdown {{ request()->is('seasons','seasons/*','prizes','trades','draft','players')?'active':'' }}">
  <div class="nav-dropdown-row">
    <a class="nav-dropdown-main-link" href="/prizes">Archive</a>
    <button type="button" class="nav-dropdown-toggle" onclick="this.closest('.nav-dropdown').classList.toggle('open')" aria-label="Open Archive menu" aria-expanded="false"><span aria-hidden="true">▾</span></button>
  </div>
  <div class="nav-dropdown-menu archive-menu">
    <a href="/teams">Franchise History</a>
    <a href="/seasons">Seasons History</a>
    <a href="/prizes">Prizes History</a>
    <a href="/trades">Trades History</a>
    <a href="/draft" onclick="if(location.pathname==='/draft'){event.preventDefault();history.replaceState(null,'','/draft');window.scrollTo({top:0,left:0,behavior:'auto'});}">Draft History</a>
    <a href="/players">Players History</a>
  </div>
</div>
<a href="/rules" class="{{ request()->is('rules')?'active':'' }}">Rules</a>
</nav></div></header>
@if($showSeasonFilter)<div class="season-filter-bar"><div class="shell"><div class="header-season-filter" role="group" aria-label="Season type">@foreach(['h2h'=>'Head-to-Head','total'=>'Total Points'] as $value=>$label)<button type="button" class="header-filter-button season-type-choice {{ in_array($seasonMode,[$value,'all'])?'active':'' }}" data-value="{{ $value }}">{{ $label }}</button>@endforeach</div></div></div>@endif
<main>@yield('content')</main>
<footer class="site-footer"><div class="shell footer-inner"><div><strong>ECFHL HISTORY</strong><br><span>2007–08 → present</span></div><div class="footer-right">Database-backed league archive</div></div></footer>
<script>(function(){const saved=localStorage.getItem('ecfhl-theme');if(saved)document.documentElement.dataset.theme=saved;if(location.pathname==='/draft'&&location.hash){history.replaceState(null,'',location.pathname+location.search);window.scrollTo(0,0);}})();function toggleTheme(){const next=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=next;localStorage.setItem('ecfhl-theme',next);}</script>
<script>
(function(){
 const button=document.getElementById('push-notifications-button');
 if(!button)return;
 const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
 const supported=('serviceWorker' in navigator)&&('PushManager' in window)&&('Notification' in window);

 const b64ToUint8=value=>{
   const padding='='.repeat((4-value.length%4)%4);
   const base64=(value+padding).replace(/-/g,'+').replace(/_/g,'/');
   const raw=atob(base64);
   return Uint8Array.from([...raw].map(ch=>ch.charCodeAt(0)));
 };

 const updateButton=async()=>{
   if(!supported){
     button.classList.add('push-blocked');
     button.title='Browser notifications are not supported here';
     button.setAttribute('aria-label','Browser notifications unavailable');
     return;
   }
   try{
     const reg=await navigator.serviceWorker.getRegistration('/push-sw.js');
     const sub=reg?await reg.pushManager.getSubscription():null;
     const enabled=!!sub && Notification.permission==='granted';
     button.classList.toggle('push-enabled',enabled);
     button.classList.toggle('push-blocked',Notification.permission==='denied');
     button.title=enabled?'Disable ECFHL notifications':(Notification.permission==='denied'?'Notifications are blocked in this browser':'Enable ECFHL notifications');
     button.setAttribute('aria-label',button.title);
   }catch(e){}
 };

 button.addEventListener('click',async()=>{
   if(!supported)return;
   if(Notification.permission==='denied'){
     alert('Notifications are blocked for this site. Enable them in your browser site settings first.');
     return;
   }

   try{
     const reg=await navigator.serviceWorker.register('/push-sw.js',{scope:'/'});
     await navigator.serviceWorker.ready;
     const existing=await reg.pushManager.getSubscription();

     if(existing){
       await fetch('/push/unsubscribe',{
         method:'POST',
         credentials:'same-origin',
         headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
         body:JSON.stringify({endpoint:existing.endpoint})
       });
       await existing.unsubscribe();
       await updateButton();
       return;
     }

     const permission=await Notification.requestPermission();
     if(permission!=='granted'){await updateButton();return;}

     const configResponse=await fetch('/push/config',{credentials:'same-origin',cache:'no-store'});
     if(!configResponse.ok)throw new Error('Could not load push configuration.');
     const config=await configResponse.json();

     const subscription=await reg.pushManager.subscribe({
       userVisibleOnly:true,
       applicationServerKey:b64ToUint8(config.publicKey)
     });

     const saveResponse=await fetch('/push/subscribe',{
       method:'POST',
       credentials:'same-origin',
       headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
       body:JSON.stringify({endpoint:subscription.endpoint})
     });
     if(!saveResponse.ok)throw new Error('Could not save push subscription.');
     const saved=await saveResponse.json();

     reg.active?.postMessage({type:'set-last-notification-id',id:saved.latestId??config.latestId??0});
     await updateButton();
   }catch(e){
     console.error('ECFHL push setup failed',e);
     alert('Could not enable ECFHL notifications in this browser.');
   }
 });

 updateButton();
})();
</script>
<script>document.querySelectorAll('.season-type-choice').forEach(button=>button.addEventListener('click',()=>{const value=button.dataset.value;const buttons=[...document.querySelectorAll('.season-type-choice')];const selected=buttons.filter(x=>x.classList.contains('active')).map(x=>x.dataset.value);const next=selected.includes(value)?selected.filter(x=>x!==value):[...selected,value];const mode=next.length===2?'all':(next[0]||'none');document.cookie='ecfhl-season-type='+mode+'; Path=/; Max-Age=31536000; SameSite=Lax';const url=new URL(location.href);url.searchParams.set('type',mode);if(/^\/seasons\//.test(url.pathname))url.pathname='/seasons';url.searchParams.delete('season');location.assign(url);}));</script>
@if(request()->is('daily-targets'))
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const setup=(id,title,withSkaterFilters=false,withGoalieFilters=false)=>{
   const section=document.getElementById(id);if(!section)return;
   const heading=section.querySelector('h2');if(heading)heading.textContent=title;
   const rows=[...section.querySelectorAll('tbody tr')].filter(row=>!row.querySelector('.empty'));
   let visible=5;
   let searchTerm='';
   const availableLines=withSkaterFilters?['1','2','3','4'].filter(line=>rows.some(row=>row.querySelector('.tips-line-'+line))):[];
   const selectedLines=new Set(availableLines);
   const selectedPp=new Set();
   const selectedGoalies=new Set(withGoalieFilters?['1','2']:[]);
   let includeInjured=true;

   const searchWrap=document.createElement('div');
   searchWrap.className='tips-search-wrap';
   searchWrap.innerHTML='<input type="search" class="tips-search" placeholder="Search '+title.replace('Available ','').toLowerCase()+'..." aria-label="Search '+title+'">';
   const sectionTitle=section.querySelector('.section-title');
   if(sectionTitle)sectionTitle.insertAdjacentElement('afterend',searchWrap);
   else if(heading)heading.insertAdjacentElement('afterend',searchWrap);

   const matchesSearch=row=>{
     if(!searchTerm)return true;
     const playerCell=row.querySelector('[data-label="Goalie"],[data-label="Player"]');
     return (playerCell?.textContent||'').toLowerCase().includes(searchTerm);
   };

   const matchesFilters=row=>{
     const isInjured=row.dataset.injured==='1';
     if(!includeInjured && isInjured) return false;
     if(includeInjured && isInjured) return true;
     if(withGoalieFilters&&selectedGoalies.size){
       const goaliePill=row.querySelector('.tips-g1,.tips-g2');
       if(!goaliePill)return false;
       const depth=goaliePill.classList.contains('tips-g1')?'1':'2';
       if(!selectedGoalies.has(depth))return false;
     }

     if(withSkaterFilters){
       if(selectedLines.size){
         const linePill=row.querySelector('.tips-line');
         if(!linePill)return false;
         const match=[...linePill.classList].find(x=>/^tips-line-[1-4]$/.test(x));
         const line=match?match.replace('tips-line-',''):null;
         if(!line||!selectedLines.has(line))return false;
       }

       if(selectedPp.size){
         const ppPill=row.querySelector('.tips-pp1,.tips-pp2');
         if(!ppPill)return false;
         const pp=ppPill.classList.contains('tips-pp1')?'1':'2';
         if(!selectedPp.has(pp))return false;
       }
     }

     return true;
   };

   const moreButton=document.createElement('button');
   moreButton.type='button';
   moreButton.className='button primary tips-see-more';
   moreButton.textContent='See more results';

   const render=()=>{
     const eligible=rows.filter(row=>matchesSearch(row)&&matchesFilters(row));
     rows.forEach(row=>row.style.display='none');
     eligible.slice(0,visible).forEach(row=>row.style.display='');
     moreButton.style.display=eligible.length>visible?'block':'none';
   };

   searchWrap.querySelector('.tips-search').addEventListener('input',event=>{
     searchTerm=event.target.value.trim().toLowerCase();
     visible=5;
     render();
   });

   const bindInjuryButton=button=>{
     if(!button)return;
     button.classList.toggle('active',includeInjured);
     button.setAttribute('aria-pressed',includeInjured?'true':'false');
     button.addEventListener('click',()=>{
       includeInjured=!includeInjured;
       button.classList.toggle('active',includeInjured);
       button.setAttribute('aria-pressed',includeInjured?'true':'false');
       visible=5;
       render();
     });
   };

   if(withGoalieFilters){
     const filterWrap=document.createElement('div');
     filterWrap.className='tips-line-pp-filters';
     filterWrap.setAttribute('role','group');
     filterWrap.setAttribute('aria-label',title+' goalie depth filters');
     filterWrap.innerHTML='<div class="tips-filter-row tips-goalie-filter-row"><button type="button" class="tips-filter-button tips-goalie-filter" data-goalie="1" aria-pressed="false">G1</button><button type="button" class="tips-filter-button tips-goalie-filter" data-goalie="2" aria-pressed="false">G2</button><button type="button" class="tips-filter-button tips-injury-filter active" aria-pressed="true">IR</button></div>';
     searchWrap.insertAdjacentElement('afterend',filterWrap);
     bindInjuryButton(filterWrap.querySelector('.tips-injury-filter'));
     filterWrap.querySelectorAll('.tips-goalie-filter').forEach(filter=>{
       filter.classList.add('active');
       filter.setAttribute('aria-pressed','true');
       filter.addEventListener('click',()=>{
       const value=filter.dataset.goalie;
       if(selectedGoalies.has(value)){selectedGoalies.delete(value);filter.classList.remove('active');filter.setAttribute('aria-pressed','false');}
       else{selectedGoalies.add(value);filter.classList.add('active');filter.setAttribute('aria-pressed','true');}
       visible=5;render();
       });
     });
   }

   if(withSkaterFilters){
     const filterWrap=document.createElement('div');
     filterWrap.className='tips-line-pp-filters';
     filterWrap.setAttribute('role','group');
     filterWrap.setAttribute('aria-label',title+' line and power play filters');
     filterWrap.innerHTML=
       '<div class="tips-filter-row tips-line-filter-row">'+
       availableLines.map(line=>'<button type="button" class="tips-filter-button tips-line-filter" data-line="'+line+'" aria-pressed="true">L'+line+'</button>').join('')+
       '<button type="button" class="tips-filter-button tips-injury-filter active" aria-pressed="true">IR</button>'+
       '</div>'+
       '<div class="tips-filter-row tips-pp-filter-row">'+
       '<button type="button" class="tips-filter-button tips-pp-filter" data-pp="1" aria-pressed="false">PP1</button>'+
       '<button type="button" class="tips-filter-button tips-pp-filter" data-pp="2" aria-pressed="false">PP2</button>'+
       '</div>';
     searchWrap.insertAdjacentElement('afterend',filterWrap);
     bindInjuryButton(filterWrap.querySelector('.tips-injury-filter'));

     filterWrap.querySelectorAll('.tips-line-filter').forEach(filter=>{
       filter.classList.add('active');
       filter.setAttribute('aria-pressed','true');
       filter.addEventListener('click',()=>{
       const value=filter.dataset.line;
       if(selectedLines.has(value)){selectedLines.delete(value);filter.classList.remove('active');filter.setAttribute('aria-pressed','false');}
       else{selectedLines.add(value);filter.classList.add('active');filter.setAttribute('aria-pressed','true');}
       visible=5;render();
       });
     });

     filterWrap.querySelectorAll('.tips-pp-filter').forEach(filter=>filter.addEventListener('click',()=>{
       const value=filter.dataset.pp;
       if(selectedPp.has(value)){selectedPp.delete(value);filter.classList.remove('active');filter.setAttribute('aria-pressed','false');}
       else{selectedPp.add(value);filter.classList.add('active');filter.setAttribute('aria-pressed','true');}
       visible=5;render();
     }));
   }

   moreButton.addEventListener('click',()=>{visible+=10;render();});
   const card=section.querySelector('.table-card');if(card)card.insertAdjacentElement('afterend',moreButton);
   render();
 };

 setup('goalies','Goaltenders',false,true);
 setup('forwards','Forwards',true,false);
 setup('defensemen','Defensemen',true,false);
});
</script>
<style>
.tips-see-more{display:block;margin:9px auto 0;min-width:0;padding:6px 10px;font-size:11px;line-height:1.1;cursor:pointer}
.tips-show-more{display:none!important}
.tips-search-wrap{margin:10px 0 8px}
.tips-search{width:min(360px,100%);border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:10px;padding:9px 12px;font-size:13px;outline:none}
.tips-search:focus{border-color:#64748b;box-shadow:0 0 0 3px rgba(100,116,139,.12)}
.tips-line-pp-filters{display:flex;flex-direction:column;align-items:flex-start;gap:7px;margin:10px 0 12px}
.tips-filter-row{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.tips-filter-button{appearance:none;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:999px;padding:7px 13px;font-weight:800;font-size:12px;cursor:pointer;transition:.15s ease}
.tips-filter-button:hover{border-color:#64748b}
.tips-injury-filter.active{background:#fee2e2;color:#b91c1c;border-color:#fca5a5;box-shadow:0 2px 8px rgba(220,38,38,.12)}
.tips-line-filter[data-line="1"].active{background:#dcfce7;color:#166534;border-color:#86efac;box-shadow:0 2px 8px rgba(22,163,74,.14)}
.tips-line-filter[data-line="2"].active{background:#fef3c7;color:#92400e;border-color:#fcd34d;box-shadow:0 2px 8px rgba(234,179,8,.14)}
.tips-line-filter[data-line="3"].active{background:#ffedd5;color:#9a3412;border-color:#fdba74;box-shadow:0 2px 8px rgba(234,88,12,.14)}
.tips-line-filter[data-line="4"].active{background:#fee2e2;color:#b91c1c;border-color:#fca5a5;box-shadow:0 2px 8px rgba(220,38,38,.14)}
.tips-goalie-filter[data-goalie="1"].active{background:#dcfce7;color:#166534;border-color:#86efac;box-shadow:0 2px 8px rgba(22,163,74,.14)}
.tips-goalie-filter[data-goalie="2"].active{background:#fef3c7;color:#92400e;border-color:#fcd34d;box-shadow:0 2px 8px rgba(234,179,8,.14)}
.tips-pp-filter[data-pp="1"].active{background:#dcfce7;color:#166534;border-color:#86efac;box-shadow:0 2px 8px rgba(22,163,74,.14)}
.tips-pp-filter[data-pp="2"].active{background:#fef3c7;color:#92400e;border-color:#fcd34d;box-shadow:0 2px 8px rgba(234,179,8,.14)}
@media(max-width:600px){.tips-line-pp-filters{margin:10px 0 12px}.tips-filter-button{padding:7px 12px}}
</style>
@endif
<style>.header-actions{display:flex;align-items:center;gap:8px;margin-left:auto}.header-theme-toggle{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}@media(min-width:901px){.header-actions{order:3}.main-nav{order:2}.header-theme-toggle{margin-left:6px}}</style>
@stack('scripts')
</body></html>