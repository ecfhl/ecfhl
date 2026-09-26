<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    @php
        if (request()->is('/')) {
            $browserTitle = 'East Coast Fantasy Hockey League';
        } elseif (request()->is('teams/*') && isset($team)) {
            $browserTitle = 'ECFHL - '.($team['team'] ?? 'Franchise');
        } elseif (request()->is('seasons/*') && isset($season)) {
            $browserTitle = 'ECFHL - '.($season['season'] ?? 'Season');
        } else {
            $pageTitles = [
                'seasons' => 'Seasons',
                'teams' => 'Franchises',
                'prizes' => 'Prizes',
                'trades' => 'Trades',
                'draft' => 'Draft',
                'players' => 'Players',
                'rules' => 'Rules',
            ];
            $browserTitle = 'ECFHL - '.($pageTitles[request()->segment(1)] ?? 'East Coast Fantasy Hockey League');
        }
    @endphp
    <title>{{ $browserTitle }}</title>
    <link rel="icon" href="/favicon.svg?v=5" type="image/svg+xml" sizes="any">
    <link rel="icon" href="/ecfhl-logo.png?v=5" type="image/png">
    <link rel="shortcut icon" href="/favicon.svg?v=5">
    <link rel="apple-touch-icon" href="/ecfhl-logo.png?v=5">
    <link rel="stylesheet" href="/app.css?v=4">
    <link rel="stylesheet" href="/header-filters.css?v=2">
</head>
<body>
<header class="site-header">
<div class="shell header-inner">
<a class="brand" href="/">
<img src="/ecfhl-logo.png" alt="ECFHL logo">
<div><strong>East Coast</strong><span>Fantasy Hockey League</span></div>
</a>
@if(!request()->is('rules'))
<div class="header-season-types" aria-label="Season type">
<a href="{{ request()->fullUrlWithQuery(['type'=>'h2h']) }}" class="header-type {{ request('type','h2h')==='h2h'?'active':'' }}">Head-to-Head</a>
<a href="{{ request()->fullUrlWithQuery(['type'=>'points']) }}" class="header-type {{ request('type')==='points'?'active':'' }}">Total Points</a>
</div>
@endif
<button class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode">◐</button>
<button class="menu-toggle" id="menuToggle" aria-label="Toggle navigation">☰</button>
<nav class="site-nav" id="siteNav">
<a href="/" class="{{ request()->is('/')?'active':'' }}">Summary</a>
<a href="/seasons" class="{{ request()->is('seasons*')?'active':'' }}">Seasons</a>
<a href="/teams" class="{{ request()->is('teams*')?'active':'' }}">Franchises</a>
<a href="/prizes" class="{{ request()->is('prizes*')?'active':'' }}">Prizes</a>
<a href="/trades" class="{{ request()->is('trades*')?'active':'' }}">Trades</a>
<a href="/draft" class="{{ request()->is('draft*')?'active':'' }}">Draft</a>
<a href="/players" class="{{ request()->is('players*')?'active':'' }}">Players</a>
<a href="/rules" class="{{ request()->is('rules*')?'active':'' }}">Rules</a>
</nav>
</div>
</header>
<main>@yield('content')</main>
<footer class="site-footer"><div class="shell"><strong>ECFHL HISTORY</strong><span>2007–08 → present</span></div></footer>
<script>
const root=document.documentElement;
const storedTheme=localStorage.getItem('ecfhl-theme');
if(storedTheme)root.dataset.theme=storedTheme;
document.getElementById('themeToggle')?.addEventListener('click',()=>{const next=root.dataset.theme==='dark'?'light':'dark';root.dataset.theme=next;localStorage.setItem('ecfhl-theme',next);});
document.getElementById('menuToggle')?.addEventListener('click',()=>document.getElementById('siteNav')?.classList.toggle('open'));
</script>
@stack('scripts')
</body>
</html>
