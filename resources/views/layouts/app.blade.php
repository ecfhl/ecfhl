<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="/submit-guard.js?v=1"></script>
    <script src="/navigation-feedback.js?v=2" defer></script>
    @php
        if (request()->is('/')) {$browserTitle='East Coast Fantasy Hockey League';}
        elseif(request()->is('teams/current/*')&&isset($teamName)){$browserTitle='ECFHL - '.$teamName;}
        elseif(request()->is('teams/*')&&isset($team)){$browserTitle='ECFHL - '.($team['team']??'Franchise');}
        elseif(request()->is('seasons/*')&&isset($season)){$browserTitle='ECFHL - '.($season['season']??'Season');}
        elseif(request()->is('teams/current')){$browserTitle='ECFHL - Live Scoring';}
        else{$pageTitles=['seasons'=>'Seasons','standings'=>'Standings','teams'=>'Franchises','prizes'=>'Prizes','trades'=>'Trades','draft'=>'Draft','players'=>'Players','daily-targets'=>'Daily Targets','job-status'=>'Collector Status','admin'=>'Admin','login'=>'Sign In','register'=>'Create Account','account'=>'Account','notifications'=>'Notifications','rules'=>'Rules'];$browserTitle='ECFHL - '.($pageTitles[request()->segment(1)]??'East Coast Fantasy Hockey League');}
        $showSeasonFilter=!request()->is('login','register','account','account/*','notifications','auth/*','rules','players','daily-targets','job-status','admin','admin/*','teams/current','teams/current/*','seasons','seasons/*','standings');if($showSeasonFilter)$seasonMode=app(\App\Support\Archive::class)->mode();
        $currentTeamMenu=\App\Support\PublicData::teamMenu();
    @endphp
    <title>{{ $browserTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=7"><link rel="shortcut icon" href="/favicon.svg?v=7"><link rel="apple-touch-icon" href="/ecfhl-logo.png?v=7"><link rel="stylesheet" href="/app.css?v=6"><link rel="stylesheet" href="/header-filters.css?v=3"><link rel="stylesheet" href="/navigation-feedback.css?v=1">
<style>
.button:disabled{opacity:.6;cursor:not-allowed}.submit-pending{cursor:wait!important}.submit-pending::before{content:'';display:inline-block;width:12px;height:12px;margin-right:7px;border:2px solid currentColor;border-right-color:transparent;border-radius:50%;vertical-align:-2px;animation:submit-spin .8s linear infinite}@keyframes submit-spin{to{transform:rotate(360deg)}}@media(prefers-reduced-motion:reduce){.submit-pending::before{animation:none}}
html,body,main{max-width:100%;overflow-x:clip}.push-picker-wrap{position:relative;display:flex;align-items:center}.push-team-picker{display:none;position:absolute;right:0;top:calc(100% + 8px);z-index:1200;width:230px;padding:10px;background:#082f4f;border:1px solid #6b88a0;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.28)}.push-team-picker.open{display:block}.push-team-picker-label{display:block;margin:0 0 6px;color:#dce6f2;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.06em}.push-team-select{width:100%;padding:7px 9px;border:1px solid rgba(255,255,255,.3);border-radius:8px;background:#0d3a5e;color:#fff;font-size:11px;font-weight:700}.push-switch-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:10px;color:#dce6f2;font-size:10px;font-weight:800}.push-switch{position:relative;display:inline-flex;align-items:center;flex:0 0 auto;width:42px;height:22px;cursor:pointer}.push-switch input{position:absolute;opacity:0;pointer-events:none}.push-switch-track{position:absolute;inset:0;border-radius:999px;background:#64748b;border:1px solid rgba(255,255,255,.25);transition:.18s ease}.push-switch-thumb{position:absolute;left:3px;top:3px;width:16px;height:16px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.3);transition:.18s ease}.push-switch input:checked~.push-switch-track{background:#22c55e;border-color:#4ade80}.push-switch input:checked~.push-switch-thumb{transform:translateX(20px)}.push-switch input:focus-visible~.push-switch-track{outline:2px solid #93c5fd;outline-offset:2px}.push-notification-toggle{border:0;background:transparent;color:inherit;font-size:18px;line-height:1;cursor:pointer;padding:7px;border-radius:8px}.push-notification-toggle:hover{background:var(--surface-2,rgba(255,255,255,.08))}.push-notification-toggle.push-enabled{background:#dcfce7;color:#166534}.push-notification-toggle.push-blocked{opacity:.5}
.nav-item-icon{display:inline-flex;width:20px;justify-content:center;align-items:center;margin-right:6px;font-size:15px;line-height:1;vertical-align:-1px}.main-nav>a,.nav-dropdown-main-link{white-space:nowrap}.main-nav{gap:2px}.main-nav a,.theme-toggle{padding-left:8px!important;padding-right:8px!important;font-size:13px}.nav-live-icon,.nav-live-text{color:#c94b52!important}.nav-live-text{font-weight:800}.nav-live-icon{font-size:14px;text-shadow:0 0 5px rgba(201,75,82,.45)}.nav-target-icon{font-size:19px}.nav-teams-icon{color:#14b8a6}.nav-dropdown{position:relative;display:flex;align-items:center}.nav-dropdown-row{display:flex;align-items:center}.nav-dropdown-main-link{display:block}.nav-dropdown-toggle{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:inherit;padding:8px 7px;cursor:pointer;border-radius:7px}.nav-dropdown.active>.nav-dropdown-row,.nav-dropdown-toggle:hover{background:var(--surface-2,rgba(255,255,255,.08));border-radius:7px}.nav-dropdown-menu{display:none;position:absolute;top:100%;left:0;z-index:1000;min-width:430px;grid-template-columns:repeat(2,minmax(190px,1fr));gap:2px;padding:8px;background:#082f4f;border:1px solid #6b88a0;border-radius:10px;box-shadow:0 12px 30px rgba(15,23,42,.28);color:#fff}.nav-dropdown:hover .nav-dropdown-menu,.nav-dropdown.open .nav-dropdown-menu{display:grid}.nav-dropdown-menu a{display:block;padding:8px 10px!important;border-radius:7px;white-space:nowrap;text-decoration:none;color:#fff!important}.nav-dropdown-menu a:hover{background:#12486f}.archive-menu{min-width:180px!important;grid-template-columns:1fr!important}.nav-dropdown-menu .nav-all-teams-link,.nav-dropdown-menu .nav-history-link{grid-column:1/-1;font-weight:800}.nav-dropdown-menu .nav-all-teams-link{border-bottom:1px solid #6b88a0;margin-bottom:4px}.nav-dropdown-menu .nav-history-link{border-top:1px solid #6b88a0;margin-top:4px;padding-top:9px!important}
@media(max-width:900px){.header-actions{min-width:0;gap:5px}.push-team-picker{position:fixed;right:10px;top:62px;width:min(240px,calc(100vw - 20px))}.push-team-select{font-size:11px}.push-notification-toggle,.header-theme-toggle{padding:5px}.nav-toggle{flex:0 0 auto}.main-nav{text-align:left}.main-nav>a{display:block;text-align:left!important;padding-left:24px!important}.nav-dropdown{display:block;width:100%}.nav-dropdown-row{display:grid;grid-template-columns:1fr auto;align-items:center;width:100%}.nav-dropdown-main-link{text-align:left!important;padding:10px 0 10px 24px!important}.nav-dropdown-toggle{width:44px;text-align:center;padding:10px 0}.nav-dropdown:hover .nav-dropdown-menu{display:none}.nav-dropdown.open .nav-dropdown-menu{display:grid!important;position:static;min-width:0;width:100%;grid-template-columns:1fr;background:transparent;border:0;box-shadow:none;padding:4px 0 8px 36px}.nav-dropdown-menu a{padding:8px 0!important;text-align:left!important}.nav-dropdown-menu .nav-history-link{border-top:1px solid var(--line);padding-top:10px!important}}

.mobile-primary-nav{display:none}
@media(max-width:900px){
 html{scroll-padding-bottom:calc(60px + env(safe-area-inset-bottom,0px))}
 body{padding-bottom:calc(60px + env(safe-area-inset-bottom,0px))}
 .mobile-primary-nav{position:fixed;left:0;right:0;bottom:0;z-index:1100;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px;padding:4px max(4px,env(safe-area-inset-right,0px)) calc(4px + env(safe-area-inset-bottom,0px)) max(4px,env(safe-area-inset-left,0px));background:var(--panel);border-top:1px solid var(--line);box-shadow:0 -4px 16px rgba(15,23,42,.15)}
 .mobile-primary-nav a{min-width:0;min-height:44px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:3px 1px;border:2px solid transparent;border-radius:7px;color:#fff;text-decoration:none;font-size:clamp(10px,2.7vw,11px);font-weight:800;line-height:1.15;text-align:center}
 .mobile-primary-nav .mobile-nav-label{display:flex;align-items:center;justify-content:center;min-height:0;white-space:nowrap}
 .mobile-primary-nav .mobile-nav-icon{font-size:15px;line-height:1}
 .mobile-primary-nav .mobile-nav-live{background:#cf0020}
 .mobile-primary-nav .mobile-nav-team{background:#007d82}
 .mobile-primary-nav .mobile-nav-standings{background:#00699f}
 .mobile-primary-nav .mobile-nav-targets{background:#ffcc25;color:#231d08}
 .mobile-primary-nav a.active{border-color:var(--text);box-shadow:0 0 0 1px var(--panel)}
 .mobile-primary-nav a:focus-visible{outline:3px solid var(--text);outline-offset:1px}
 .mobile-primary-nav a:active{filter:brightness(.9)}
}
</style>
</head>
<body>
<header class="site-header"><div class="shell nav-wrap"><div class="brand-area"><a class="brand" href="/" data-loading-label="Overview"><img class="brand-logo" src="{{ asset('ecfhl-logo.png') }}" alt="ECFHL league logo"><span class="brand-copy"><strong>EAST COAST</strong><small>FANTASY HOCKEY LEAGUE</small></span></a></div><div class="header-actions"><a class="push-notification-toggle" href="/notifications" data-loading-label="Notifications" aria-label="Notification settings" title="Notification settings">🔔</a><button class="theme-toggle header-theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch theme">◐</button><button class="nav-toggle" type="button" aria-label="Toggle navigation" onclick="document.body.classList.toggle('nav-open')">☰</button></div><nav class="main-nav" id="main-navigation">
@guest<a href="/register" class="nav-create-account">Create account</a>@endguest
<a href="/" class="{{ request()->is('/')?'active':'' }}"><span class="nav-item-icon">⌂</span>Overview</a>
<a id="my-team-nav-link" class="my-team-link" data-my-team-link href="#"><span class="nav-item-icon">★</span>My Team</a>
<a href="/teams/current" class="{{ request()->is('teams/current','teams/current/*')?'active':'' }}"><span class="nav-item-icon nav-live-icon">●</span><span class="nav-live-text">Live Scoring</span></a>
<a href="/standings" class="{{ request()->is('standings')?'active':'' }}"><span class="nav-item-icon">🏆</span>Standings</a>
<a href="/daily-targets" class="{{ request()->is('daily-targets')?'active':'' }}"><span class="nav-item-icon nav-target-icon">🎯</span>Daily Targets</a>
<div class="nav-dropdown {{ request()->is('teams')||request()->is('teams/*')&&!request()->is('teams/current','teams/current/*')?'active':'' }}">
  <div class="nav-dropdown-row">
    <a class="nav-dropdown-main-link" href="/standings"><span class="nav-item-icon nav-teams-icon">👥</span>Teams</a>
    <button type="button" class="nav-dropdown-toggle" onclick="const menu=this.closest('.nav-dropdown');menu.classList.remove('menu-dismissed');this.setAttribute('aria-expanded',String(menu.classList.toggle('open')))" aria-label="Open Teams menu" aria-expanded="false"><span aria-hidden="true">▾</span></button>
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
    <a class="nav-dropdown-main-link" href="/teams"><span class="nav-item-icon">📖</span>Archive</a>
    <button type="button" class="nav-dropdown-toggle" onclick="const menu=this.closest('.nav-dropdown');menu.classList.remove('menu-dismissed');this.setAttribute('aria-expanded',String(menu.classList.toggle('open')))" aria-label="Open Archive menu" aria-expanded="false"><span aria-hidden="true">▾</span></button>
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
<a href="/rules" class="{{ request()->is('rules')?'active':'' }}"><span class="nav-item-icon">🔨</span>Rules</a>
@if(auth()->user()?->is_admin)
<div class="nav-dropdown {{ request()->is('admin','admin/*','job-status')?'active':'' }}">
  <div class="nav-dropdown-row">
    <a class="nav-dropdown-main-link" href="/admin"><span class="nav-item-icon">⚙</span>Admin</a>
    <button type="button" class="nav-dropdown-toggle" onclick="const menu=this.closest('.nav-dropdown');menu.classList.remove('menu-dismissed');this.setAttribute('aria-expanded',String(menu.classList.toggle('open')))" aria-label="Open Admin menu" aria-expanded="false"><span aria-hidden="true">▾</span></button>
  </div>
  <div class="nav-dropdown-menu archive-menu">
    <a href="/job-status">Collector Status</a>
    <a href="/admin/advisors">Advisors</a>
    <a href="/admin/team-images">Team Images</a>
  </div>
</div>
@endif
<a href="/notifications" class="{{ request()->is('notifications')?'active':'' }}">Notifications</a>
@auth<a href="/account" class="{{ request()->is('account','account/*')?'active':'' }}">Account</a>@else<a href="/login">Sign in</a>@endauth
</nav></div></header>
@if($showSeasonFilter)<div class="season-filter-bar"><div class="shell"><div class="header-season-filter" role="group" aria-label="Season type">@foreach(['h2h'=>'Head-to-Head','total'=>'Total Points'] as $value=>$label)<button type="button" class="header-filter-button season-type-choice {{ in_array($seasonMode,[$value,'all'])?'active':'' }}" data-value="{{ $value }}">{{ $label }}</button>@endforeach</div></div></div>@endif
<main>@yield('content')</main>

<nav class="mobile-primary-nav" aria-label="Primary navigation">
  <a class="mobile-nav-live {{ request()->is('teams/current')?'active':'' }}" href="/teams/current" data-loading-label="Live Scoring" @if(request()->is('teams/current')) aria-current="page" @endif><span class="mobile-nav-icon" aria-hidden="true">●</span><span class="mobile-nav-label">Live Scoring</span></a>
  <a id="mobile-my-team-nav-link" class="mobile-nav-team" data-my-team-link href="{{ auth()->user()?->claim ? '/teams/current/'.\Illuminate\Support\Str::slug(auth()->user()->claim->team_name) : '/account/claim-team' }}" data-loading-label="My Team"><span class="mobile-nav-icon" aria-hidden="true">★</span><span class="mobile-nav-label">My Team</span></a>
  <a class="mobile-nav-standings {{ request()->is('standings')?'active':'' }}" href="/standings" data-loading-label="Standings" @if(request()->is('standings')) aria-current="page" @endif><span class="mobile-nav-icon" aria-hidden="true">🏆</span><span class="mobile-nav-label">Standings</span></a>
  <a class="mobile-nav-targets {{ request()->is('daily-targets')?'active':'' }}" href="/daily-targets" data-loading-label="Daily Targets" @if(request()->is('daily-targets')) aria-current="page" @endif><span class="mobile-nav-icon" aria-hidden="true">🎯</span><span class="mobile-nav-label">Daily Targets</span></a>
</nav>
<footer class="site-footer"><div class="shell footer-inner"><div><strong>ECFHL HISTORY</strong><br><span>2007–08 → present</span></div><div class="footer-right">Database-backed league archive</div></div></footer>
<div id="navigation-loading" class="navigation-loading" hidden>
  <div class="navigation-loading-card"><span class="navigation-spinner" aria-hidden="true"></span><span id="navigation-loading-message" role="status" aria-live="polite">Loading…</span><button id="navigation-cancel" type="button" hidden>Cancel loading</button></div>
</div>
@guest
@unless(request()->is('login','register','auth/*'))
<dialog id="guest-signup-dialog" class="guest-signup-dialog" aria-labelledby="guest-signup-title" aria-describedby="guest-signup-description">
  <button class="guest-signup-close" type="button" data-dismiss-signup aria-label="Close account invitation">×</button>
  <img class="guest-signup-logo" src="/ecfhl-logo.png" alt="">
  <h2 id="guest-signup-title">Claim your ECFHL team</h2>
  <p id="guest-signup-description" class="guest-signup-description">Create an account to choose your team, save your preferences, and get scoring and goalie alerts.</p>
  <a class="button primary guest-signup-primary" href="/register" autofocus>Create account</a>
  <button class="guest-signup-browse" type="button" data-dismiss-signup>Continue browsing</button>
  <p class="guest-signup-signin">Already have an account? <a href="/login">Sign in</a></p>
</dialog>
@endunless
@endguest
<script>(function(){const saved=localStorage.getItem('ecfhl-theme');if(saved)document.documentElement.dataset.theme=saved;if(location.pathname==='/draft'&&location.hash){history.replaceState(null,'',location.pathname+location.search);window.scrollTo(0,0);}})();function toggleTheme(){const next=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=next;localStorage.setItem('ecfhl-theme',next);}</script>
<script>
(function(){
 const teamId=@json(auth()->user()?->claim?->fantasy_team_id);
 const teamUrl=@json(auth()->user()?->claim ? '/teams/current/'.\Illuminate\Support\Str::slug(auth()->user()->claim->team_name) : null);
 let savedTeamUrl=null;
 try{
   if(teamId&&teamUrl){localStorage.setItem('ecfhl-notification-team-id',teamId);localStorage.setItem('ecfhl-notification-team-url',teamUrl);}
   savedTeamUrl=localStorage.getItem('ecfhl-notification-team-url');
 }catch(_){}
 const links=document.querySelectorAll('[data-my-team-link]');
 const url=teamUrl||savedTeamUrl;
 links.forEach(link=>{link.href=url||'/account/claim-team';if(url&&location.pathname===url){link.classList.add('active');link.setAttribute('aria-current','page');}});
 if('serviceWorker' in navigator)navigator.serviceWorker.register('/push-sw.js',{scope:'/'}).catch(()=>{});
})();
</script>
<script>document.querySelectorAll('.season-type-choice').forEach(button=>button.addEventListener('click',()=>{const value=button.dataset.value;const buttons=[...document.querySelectorAll('.season-type-choice')];const selected=buttons.filter(x=>x.classList.contains('active')).map(x=>x.dataset.value);const next=selected.includes(value)?selected.filter(x=>x!==value):[...selected,value];const mode=next.length===2?'all':(next[0]||'none');document.cookie='ecfhl-season-type='+mode+'; Path=/; Max-Age=31536000; SameSite=Lax';const url=new URL(location.href);url.searchParams.set('type',mode);if(/^\/seasons\//.test(url.pathname))url.pathname='/seasons';url.searchParams.delete('season');location.assign(url);}));</script>
@if(request()->is('daily-targets'))
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const setup=(id,title,withSkaterFilters=false,withGoalieFilters=false)=>{
   const section=document.getElementById(id);if(!section)return;
   const heading=section.querySelector('h2');const rows=[...section.querySelectorAll('tbody tr')].filter(row=>!row.querySelector('.empty'));
   if(heading)heading.textContent=title+' ('+rows.length+')';
   let visible=5;
   let searchTerm='';
   const availableLines=withSkaterFilters?['1','2','3','4'].filter(line=>rows.some(row=>row.querySelector('.tips-line-'+line))):[];
   const selectedLines=new Set(withSkaterFilters?availableLines:[]);
   const selectedPp=new Set(withSkaterFilters?['1','2']:[]);
   const selectedGoalies=new Set(withGoalieFilters?['1','2']:[]);
   let filterInjured=false;

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
     let matchesPositionFilters=true;

     if(withGoalieFilters&&selectedGoalies.size){
       const goaliePill=row.querySelector('.tips-g1,.tips-g2');
       if(!goaliePill){
         matchesPositionFilters=false;
       }else{
         const depth=goaliePill.classList.contains('tips-g1')?'1':'2';
         if(!selectedGoalies.has(depth))matchesPositionFilters=false;
       }
     }

     if(withSkaterFilters){
       if(selectedLines.size){
         const linePill=row.querySelector('.tips-line');
         if(!linePill){
           matchesPositionFilters=false;
         }else{
           const match=[...linePill.classList].find(x=>/^tips-line-[1-4]$/.test(x));
           const line=match?match.replace('tips-line-',''):null;
           if(!line||!selectedLines.has(line))matchesPositionFilters=false;
         }
       }

       if(selectedPp.size){
         const ppPill=row.querySelector('.tips-pp1,.tips-pp2');
         if(!ppPill){
           matchesPositionFilters=false;
         }else{
           const pp=ppPill.classList.contains('tips-pp1')?'1':'2';
           if(!selectedPp.has(pp))matchesPositionFilters=false;
         }
       }
     }

     // IR is additive: show injured players OR players matching the active
     // goalie/line filters. It never narrows the current filter selection.
     return filterInjured ? (isInjured || matchesPositionFilters) : matchesPositionFilters;
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
     if(heading)heading.textContent=title+' ('+eligible.length+')';
   };

   searchWrap.querySelector('.tips-search').addEventListener('input',event=>{
     searchTerm=event.target.value.trim().toLowerCase();
     visible=5;
     render();
   });

   const bindInjuryButton=button=>{
     if(!button)return;
     button.classList.remove('active');
     button.setAttribute('aria-pressed','false');
     button.addEventListener('click',()=>{
       filterInjured=!filterInjured;
       button.classList.toggle('active',filterInjured);
       button.setAttribute('aria-pressed',filterInjured?'true':'false');
       visible=5;
       render();
     });
   };

   if(withGoalieFilters){
     const filterWrap=document.createElement('div');
     filterWrap.className='tips-line-pp-filters';
     filterWrap.setAttribute('role','group');
     filterWrap.setAttribute('aria-label',title+' goalie depth filters');
     filterWrap.innerHTML='<div class="tips-filter-row tips-goalie-filter-row"><button type="button" class="tips-filter-button tips-goalie-filter" data-goalie="1" aria-pressed="false">G1</button><button type="button" class="tips-filter-button tips-goalie-filter" data-goalie="2" aria-pressed="false">G2</button><button type="button" class="tips-filter-button tips-injury-filter" aria-pressed="false">IR</button></div>';
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
       availableLines.map(line=>'<button type="button" class="tips-filter-button tips-line-filter" data-line="'+line+'" aria-pressed="false">L'+line+'</button>').join('')+
       '<button type="button" class="tips-filter-button tips-injury-filter" aria-pressed="false">IR</button>'+
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

     filterWrap.querySelectorAll('.tips-pp-filter').forEach(filter=>{
       filter.classList.add('active');
       filter.setAttribute('aria-pressed','true');
       filter.addEventListener('click',()=>{
         const value=filter.dataset.pp;
         if(selectedPp.has(value)){selectedPp.delete(value);filter.classList.remove('active');filter.setAttribute('aria-pressed','false');}
         else{selectedPp.add(value);filter.classList.add('active');filter.setAttribute('aria-pressed','true');}
         visible=5;render();
       });
     });
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
.tips-goalie-filter:not(.active){background:#e5e7eb!important;color:#374151!important;border-color:#d1d5db!important;box-shadow:none!important}
.tips-pp-filter[data-pp="1"].active{background:#dcfce7;color:#166534;border-color:#86efac;box-shadow:0 2px 8px rgba(22,163,74,.14)}
.tips-pp-filter[data-pp="2"].active{background:#fef3c7;color:#92400e;border-color:#fcd34d;box-shadow:0 2px 8px rgba(234,179,8,.14)}
@media(max-width:600px){.tips-line-pp-filters{margin:10px 0 12px}.tips-filter-button{padding:7px 12px}}
</style>
@endif
<style>.header-actions{display:flex;align-items:center;gap:8px;margin-left:auto}.header-theme-toggle{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}@media(min-width:901px){.header-actions{order:3}.main-nav{order:2}.header-theme-toggle{margin-left:6px}}</style>
<style>
.score-up{color:#16834f!important}.score-down{color:#dc2626!important}.score-same{color:#111827!important}
.team-icon-uploader{appearance:none;border:0;background:transparent;padding:0;margin:0;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex:0 0 auto;border-radius:0;box-shadow:none}
.team-icon-uploader:hover{filter:brightness(.96)}
.team-icon-uploader:focus-visible{outline:2px solid #60a5fa;outline-offset:3px}
.team-icon-uploader img{display:block;width:46px;height:46px;object-fit:contain;border-radius:0;box-shadow:none;background:transparent}
.team-icon-modal{position:fixed;inset:0;z-index:5000;display:none;align-items:center;justify-content:center;padding:24px;background:rgba(2,6,23,.82);backdrop-filter:blur(3px)}
.team-icon-modal.open{display:flex}
.team-icon-modal-card{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;max-width:min(92vw,760px);max-height:88vh}
.team-icon-modal-loader{display:none;width:46px;height:46px;border:4px solid rgba(255,255,255,.28);border-top-color:#fff;border-radius:50%;animation:team-icon-spin .75s linear infinite}.team-icon-modal.loading .team-icon-modal-loader{display:block}.team-icon-modal.loading .team-icon-modal-image{display:none}@keyframes team-icon-spin{to{transform:rotate(360deg)}}
.team-icon-modal-image{display:block;max-width:100%;max-height:calc(88vh - 58px);width:auto;height:auto;border-radius:0;box-shadow:none;background:transparent}
.team-icon-modal-actions{display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap}
.team-icon-advisor-name{display:none;align-items:center;gap:7px}
.team-icon-advisor-name.open{display:flex}
.team-icon-advisor-label{font-size:11px;font-weight:800;color:#fff}
.team-icon-advisor-input{width:150px;padding:8px 9px;border:1px solid rgba(255,255,255,.3);border-radius:8px;background:#fff;color:#0f172a;font-size:12px;font-weight:700}
.team-icon-advisor-save,.team-icon-modal-upload{appearance:none;border:1px solid rgba(255,255,255,.22);background:#0b5f9e;color:#fff;border-radius:9px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2)}
.team-icon-advisor-save:hover,.team-icon-modal-upload:hover{background:#0d6fb8}
.team-icon-advisor-save:disabled,.team-icon-modal-upload:disabled{opacity:.6;cursor:wait}
.team-icon-modal-upload{appearance:none;border:1px solid rgba(255,255,255,.22);background:#0b5f9e;color:#fff;border-radius:9px;padding:8px 12px;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.2)}
.team-icon-modal-upload:hover{background:#0d6fb8}
.team-icon-modal-upload:disabled{opacity:.6;cursor:wait}
.team-icon-modal-close{position:absolute;top:-14px;right:-14px;width:38px;height:38px;border:0;border-radius:50%;background:#fff;color:#0f172a;font-size:24px;font-weight:900;line-height:1;cursor:pointer;box-shadow:0 4px 16px rgba(0,0,0,.28)}
.team-icon-modal-close:hover{background:#f1f5f9}
.team-icon-modal-close:focus-visible{outline:2px solid #60a5fa;outline-offset:2px}
@media(max-width:700px){.team-icon-uploader img{width:40px;height:40px;border-radius:0.team-icon-modal{padding:16px}.team-icon-modal-close{top:-10px;right:-8px}}
</style>
<div id="team-icon-modal" class="team-icon-modal" role="dialog" aria-modal="true" aria-label="Team icon preview" aria-hidden="true">
  <div class="team-icon-modal-card">
    <div class="team-icon-modal-loader" role="status" aria-label="Loading full-size team logo"></div>
    <img id="team-icon-modal-image" class="team-icon-modal-image" alt="">
    <div class="team-icon-modal-actions">
      @if(auth()->user()?->is_admin)<div id="team-icon-advisor-name" class="team-icon-advisor-name">
        <label class="team-icon-advisor-label" for="team-icon-advisor-input">First Name</label>
        <input id="team-icon-advisor-input" class="team-icon-advisor-input" type="text" maxlength="40" autocomplete="off">
        <button id="team-icon-advisor-save" class="team-icon-advisor-save" type="button">Save</button>
      </div>@endif

      <button id="team-icon-modal-upload" class="team-icon-modal-upload" type="button" hidden>Change Image</button>
      <input id="team-icon-modal-file" type="file" accept="image/png,image/jpeg,image/webp" hidden>
    </div>
    <button id="team-icon-modal-close" class="team-icon-modal-close" type="button" aria-label="Close team icon preview">×</button>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('team-icon-modal');
  const modalImage=document.getElementById('team-icon-modal-image');
  const closeButton=document.getElementById('team-icon-modal-close');
  const uploadButton=document.getElementById('team-icon-modal-upload');
  const fileInput=document.getElementById('team-icon-modal-file');
  const advisorNameRow=document.getElementById('team-icon-advisor-name');
  const advisorNameInput=document.getElementById('team-icon-advisor-input');
  const advisorNameSave=document.getElementById('team-icon-advisor-save');
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const ownedTeamSlug=@json(auth()->user()?->claim ? \Illuminate\Support\Str::slug(auth()->user()->claim->team_name) : null);
  const isAdmin=@json((bool)auth()->user()?->is_admin);
  if(!modal||!modalImage||!closeButton||!fileInput)return;

  let lastTrigger=null;
  let activeSlug='';
  let activeAdvisorKey='';

  const closeModal=()=>{
    modal.classList.remove('open','loading');
    modal.setAttribute('aria-hidden','true');
    document.body.style.removeProperty('overflow');
    fileInput.value='';
    fileInput.disabled=false;
    if(uploadButton)uploadButton.hidden=true;
    activeAdvisorKey='';
    advisorNameRow?.classList.remove('open');
    lastTrigger?.focus();
  };

  document.querySelectorAll('[data-team-icon-viewer]').forEach(button=>{
    button.addEventListener('click',()=>{
      const img=button.querySelector('img');
      if(!img)return;
      lastTrigger=button;
      activeSlug=button.dataset.teamSlug||'';
      activeAdvisorKey=button.dataset.advisorKey||'';
      const fullSrc=img.dataset.fullSrc||img.currentSrc||img.src;
      modal.classList.add('loading');
      modalImage.removeAttribute('src');
      modalImage.alt=img.alt||'Team icon';
      modalImage.onload=()=>modal.classList.remove('loading');
      modalImage.onerror=()=>modal.classList.remove('loading');
      modalImage.src=fullSrc;
      if(advisorNameRow&&advisorNameInput){
        advisorNameInput.value='';
        advisorNameRow.classList.remove('open');
      }
      if(activeAdvisorKey){
        if(uploadButton)uploadButton.hidden=true;
        fileInput.disabled=true;
      }else{
        const canChangeImage=!!activeSlug && (isAdmin || ownedTeamSlug===activeSlug);
        if(uploadButton)uploadButton.hidden=!canChangeImage;
        fileInput.disabled=!canChangeImage;
      }
      modal.classList.add('open');
      modal.setAttribute('aria-hidden','false');
      document.body.style.overflow='hidden';
      closeButton.focus();
    });
  });

  advisorNameSave?.addEventListener('click',async()=>{
    if(advisorNameSave.disabled)return;
    if(!activeAdvisorKey||!advisorNameInput)return;
    const firstName=advisorNameInput.value.trim();
    if(!firstName){
      alert('First name is required.');
      advisorNameInput.focus();
      return;
    }

    advisorNameSave.disabled=true;
    advisorNameSave.textContent='Saving...';
    try{
      const response=await fetch('/lineup-advisors/'+encodeURIComponent(activeAdvisorKey)+'/profile',{
        method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:JSON.stringify({first_name:firstName})
      });
      if(!response.ok){
        let message='Could not save advisor name.';
        try{const data=await response.json();message=data.message||message;}catch(e){}
        throw new Error(message);
      }
      const data=await response.json();
      const savedName=data.first_name||firstName;
      document.querySelectorAll('[data-advisor-display-name="'+CSS.escape(activeAdvisorKey)+'"]').forEach(el=>el.textContent=savedName);
      document.querySelectorAll('[data-team-icon-viewer][data-advisor-key="'+CSS.escape(activeAdvisorKey)+'"]').forEach(button=>{
        button.dataset.advisorFirstName=savedName;
        button.title='View '+savedName+' advisor image';
        button.setAttribute('aria-label','View '+savedName+' Lineup Advisor image');
        const img=button.querySelector('img');
        if(img)img.alt=savedName+', Lineup Advisor';
      });
    }catch(error){
      alert(error.message||'Could not save advisor name.');
    }finally{
      advisorNameSave.disabled=false;
      advisorNameSave.textContent='Save';
    }
  });

  uploadButton?.addEventListener('click',()=>fileInput.click());
  fileInput.addEventListener('change',async()=>{
    const file=fileInput.files?.[0];
    if(!file||!activeSlug)return;
    if(file.size>2*1024*1024){
      alert('Team icon must be 2 MB or smaller.');
      fileInput.value='';
      return;
    }

    const form=new FormData();
    form.append('image',file);
    if(uploadButton)uploadButton.disabled=true;
    if(uploadButton)uploadButton.textContent='Uploading...';

    try{
      const response=await fetch('/team-icons/'+encodeURIComponent(activeSlug),{
        method:'POST',
        headers:{'X-CSRF-TOKEN':csrf,'Accept':'application/json'},
        body:form
      });
      if(!response.ok){
        let message='Could not upload team icon.';
        try{const data=await response.json();message=data.message||message;}catch(e){}
        throw new Error(message);
      }
      const data=await response.json();
      const freshUrl=(data.url||('/team-icons/'+activeSlug))+(String(data.url||'').includes('?')?'&':'?')+'t='+Date.now();
      modalImage.src=freshUrl;
      document.querySelectorAll('[data-team-icon-viewer][data-team-slug="'+CSS.escape(activeSlug)+'"] img').forEach(img=>{img.dataset.fullSrc=freshUrl;img.removeAttribute('srcset');img.src=data.thumbnail_url||('/team-icons/'+encodeURIComponent(activeSlug)+'/thumbnail?size=160&t='+Date.now());});
    }catch(error){
      alert(error.message||'Could not upload team icon.');
    }finally{
      if(uploadButton)uploadButton.disabled=false;
      if(uploadButton)uploadButton.textContent='Change Image';
      fileInput.value='';
    }
  });

  closeButton.addEventListener('click',closeModal);
  modal.addEventListener('click',event=>{if(event.target===modal)closeModal();});
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&modal.classList.contains('open'))closeModal();});
});
</script>
@if(request()->is('daily-targets','teams/current/*'))
<link rel="stylesheet" href="/goalie-watches.css?v=1">
<script src="/goalie-watches.js?v=2" defer></script>
@endif
@stack('scripts')
</body></html>
