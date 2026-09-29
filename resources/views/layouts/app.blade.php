<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    @php
        if (request()->is('/')) {$browserTitle='East Coast Fantasy Hockey League';}
        elseif(request()->is('teams/*')&&isset($team)){$browserTitle='ECFHL - '.($team['team']??'Franchise');}
        elseif(request()->is('seasons/*')&&isset($season)){$browserTitle='ECFHL - '.($season['season']??'Season');}
        else{$pageTitles=['seasons'=>'Seasons','teams'=>'Franchises','prizes'=>'Prizes','trades'=>'Trades','draft'=>'Draft','players'=>'Players','ai-tips'=>'AI Tips','rules'=>'Rules'];$browserTitle='ECFHL - '.($pageTitles[request()->segment(1)]??'East Coast Fantasy Hockey League');}
        $showSeasonFilter=!request()->is('rules','players','ai-tips');if($showSeasonFilter)$seasonMode=app(\App\Support\Archive::class)->mode();
    @endphp
    <title>{{ $browserTitle }}</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=7"><link rel="shortcut icon" href="/favicon.svg?v=7"><link rel="apple-touch-icon" href="/ecfhl-logo.png?v=7"><link rel="stylesheet" href="/app.css?v=5"><link rel="stylesheet" href="/header-filters.css?v=3">
</head>
<body>
<header class="site-header"><div class="shell nav-wrap"><div class="brand-area"><a class="brand" href="/"><img class="brand-logo" src="{{ asset('ecfhl-logo.png') }}" alt="ECFHL league logo"><span class="brand-copy"><strong>EAST COAST</strong><small>FANTASY HOCKEY LEAGUE</small></span></a></div><div class="header-actions"><button class="theme-toggle header-theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch theme">◐</button><button class="nav-toggle" type="button" aria-label="Toggle navigation" onclick="document.body.classList.toggle('nav-open')">☰</button></div><nav class="main-nav">@foreach(['/'=>'Overview','/ai-tips'=>'AI Tips','/seasons'=>'Seasons','/teams'=>'Franchises','/prizes'=>'Prizes','/trades'=>'Trades','/draft'=>'Draft','/players'=>'Players','/rules'=>'Rules'] as $url=>$label)<a href="{{ $url }}" class="{{ request()->is(ltrim($url,'/'))||($url==='/'&&request()->is('/'))?'active':'' }}" @if($url==='/draft') onclick="if(location.pathname==='/draft'){event.preventDefault();history.replaceState(null,'','/draft');window.scrollTo({top:0,left:0,behavior:'auto'});}" @endif>{{ $label }}</a>@endforeach</nav></div></header>
@if($showSeasonFilter)<div class="season-filter-bar"><div class="shell"><div class="header-season-filter" role="group" aria-label="Season type">@foreach(['h2h'=>'Head-to-Head','total'=>'Total Points'] as $value=>$label)<button type="button" class="header-filter-button season-type-choice {{ in_array($seasonMode,[$value,'all'])?'active':'' }}" data-value="{{ $value }}">{{ $label }}</button>@endforeach</div></div></div>@endif
<main>@yield('content')</main>
<footer class="site-footer"><div class="shell footer-inner"><div><strong>ECFHL HISTORY</strong><br><span>2007–08 → present</span></div><div class="footer-right">Database-backed league archive</div></div></footer>
<script>(function(){const saved=localStorage.getItem('ecfhl-theme');if(saved)document.documentElement.dataset.theme=saved;if(location.pathname==='/draft'&&location.hash){history.replaceState(null,'',location.pathname+location.search);window.scrollTo(0,0);}})();function toggleTheme(){const next=document.documentElement.dataset.theme==='dark'?'light':'dark';document.documentElement.dataset.theme=next;localStorage.setItem('ecfhl-theme',next);}</script>
<script>document.querySelectorAll('.season-type-choice').forEach(button=>button.addEventListener('click',()=>{const value=button.dataset.value;const buttons=[...document.querySelectorAll('.season-type-choice')];const selected=buttons.filter(x=>x.classList.contains('active')).map(x=>x.dataset.value);const next=selected.includes(value)?selected.filter(x=>x!==value):[...selected,value];const mode=next.length===2?'all':(next[0]||'none');document.cookie='ecfhl-season-type='+mode+'; Path=/; Max-Age=31536000; SameSite=Lax';const url=new URL(location.href);url.searchParams.set('type',mode);if(/^\/seasons\//.test(url.pathname))url.pathname='/seasons';url.searchParams.delete('season');location.assign(url);}));</script>
@if(request()->is('ai-tips'))
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const setups=[];
 const setup=(id,title,withPpFilters=false)=>{
   const section=document.getElementById(id);if(!section)return;
   const heading=section.querySelector('h2');if(heading)heading.textContent=title;
   const rows=[...section.querySelectorAll('tbody tr')].filter(row=>!row.querySelector('.empty'));
   let visible=10;
   const selectedPp=new Set();
   let filterWrap=null;
   const matchesPp=row=>{if(!withPpFilters||!selectedPp.size)return true;const pill=row.querySelector('.tips-pp1,.tips-pp2');if(!pill)return false;return selectedPp.has(pill.classList.contains('tips-pp1')?'1':'2');};
   const button=document.createElement('button');button.type='button';button.className='button primary tips-see-more';button.textContent='See more results';
   const render=()=>{const eligible=rows.filter(matchesPp);rows.forEach(row=>row.style.display='none');eligible.slice(0,visible).forEach(row=>row.style.display='');button.style.display=eligible.length>visible?'block':'none';};
   button.addEventListener('click',()=>{visible+=10;render();});
   if(withPpFilters){
     filterWrap=document.createElement('div');filterWrap.className='tips-pp-filters';filterWrap.setAttribute('role','group');filterWrap.setAttribute('aria-label',title+' power play unit');filterWrap.innerHTML='<button type="button" class="tips-pp-filter" data-pp="1" aria-pressed="false">PP1</button><button type="button" class="tips-pp-filter" data-pp="2" aria-pressed="false">PP2</button>';
     const sectionTitle=section.querySelector('.section-title');
     if(sectionTitle)sectionTitle.insertAdjacentElement('afterend',filterWrap);else if(heading)heading.insertAdjacentElement('afterend',filterWrap);
     filterWrap.querySelectorAll('.tips-pp-filter').forEach(filter=>filter.addEventListener('click',()=>{const pp=filter.dataset.pp;if(selectedPp.has(pp)){selectedPp.delete(pp);filter.classList.remove('active');filter.setAttribute('aria-pressed','false');}else{selectedPp.add(pp);filter.classList.add('active');filter.setAttribute('aria-pressed','true');}visible=10;render();}));
   }
   const card=section.querySelector('.table-card');if(card)card.insertAdjacentElement('afterend',button);
   setups.push({id,render});render();
 };
 setup('goalies','Available goalies');
 setup('forwards','Available forwards',true);
 setup('defensemen','Available defensemen',true);
});
</script>
<style>.tips-see-more{display:block;margin:14px auto 0;min-width:180px;cursor:pointer}.tips-pp-filters{display:flex;align-items:center;gap:8px;margin:10px 0 12px}.tips-pp-filter{appearance:none;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:999px;padding:8px 15px;font-weight:800;font-size:12px;cursor:pointer;transition:.15s ease}.tips-pp-filter:hover{border-color:#8b5cf6}.tips-pp-filter[data-pp="1"].active{background:#7c3aed;color:#fff;border-color:#6d28d9;box-shadow:0 2px 8px rgba(124,58,237,.22)}.tips-pp-filter[data-pp="2"].active{background:#ddd6fe;color:#4c1d95;border-color:#a78bfa;box-shadow:0 2px 8px rgba(124,58,237,.14)}@media(max-width:600px){.tips-pp-filters{margin:10px 0 12px}}</style>
@endif
<style>.header-actions{display:flex;align-items:center;gap:8px;margin-left:auto}.header-theme-toggle{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}@media(min-width:901px){.header-actions{order:3}.main-nav{order:2}.header-theme-toggle{margin-left:6px}}</style>
@stack('scripts')
</body></html>