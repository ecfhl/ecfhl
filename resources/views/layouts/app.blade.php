<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'ECFHL History')</title>
    <link rel="icon" type="image/png" href="{{ asset('ecfhl-logo.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('ecfhl-logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('ecfhl-logo.png') }}">
    <link rel="stylesheet" href="/app.css?v=4">
    <link rel="stylesheet" href="/header-filters.css?v=2">
</head>
<body>
<header class="site-header">
    <div class="shell nav-wrap">
        <div class="brand-area">
            <a class="brand" href="/">
                <img class="brand-logo" src="{{ asset('ecfhl-logo.png') }}" alt="ECFHL league logo">
                <span class="brand-copy"><strong>EAST COAST</strong><small>FANTASY HOCKEY LEAGUE</small></span>
            </a>
            @if(!request()->is('rules'))
                @php($seasonMode = app(\App\Support\Archive::class)->mode())
                <div class="header-season-filter" role="group" aria-label="Season type">
                    @foreach(['h2h'=>'Head-to-Head','total'=>'Total Points'] as $value=>$label)
                        <button type="button" class="header-filter-button season-type-choice {{ in_array($seasonMode,[$value,'all'])?'active':'' }}" data-value="{{ $value }}">{{ $label }}</button>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="header-actions">
            <button class="theme-toggle header-theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch theme">◐</button>
            <button class="nav-toggle" type="button" aria-label="Toggle navigation" onclick="document.body.classList.toggle('nav-open')">☰</button>
        </div>
        <nav class="main-nav">
            @foreach ([
                '/' => 'Overview',
                '/seasons' => 'Seasons',
                '/teams' => 'Franchises',
                '/prizes' => 'Prizes',
                '/trades' => 'Trades',
                '/draft' => 'Draft',
                '/players' => 'Players',
                '/rules' => 'Rules',
            ] as $url => $label)
                <a href="{{ $url }}" class="{{ request()->is(ltrim($url,'/')) || ($url==='/' && request()->is('/')) ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</header>

<main>@yield('content')</main>

<footer class="site-footer">
    <div class="shell footer-inner">
        <div><strong>ECFHL HISTORY</strong><br><span>2007–08 → present</span></div>
        <div class="footer-right">Database-backed league archive</div>
    </div>
</footer>

<script>
(function(){
    const saved = localStorage.getItem('ecfhl-theme');
    if(saved) document.documentElement.dataset.theme = saved;
})();
function toggleTheme(){
    const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = next;
    localStorage.setItem('ecfhl-theme', next);
}
</script>
<script>
document.querySelectorAll('.season-type-choice').forEach(button=>button.addEventListener('click',()=>{
 const value=button.dataset.value;
 const buttons=[...document.querySelectorAll('.season-type-choice')];
 const selected=buttons.filter(x=>x.classList.contains('active')).map(x=>x.dataset.value);
 const next=selected.includes(value)?selected.filter(x=>x!==value):[...selected,value];
 const mode=next.length===2?'all':(next[0]||'none');
 document.cookie='ecfhl-season-type='+mode+'; Path=/; Max-Age=31536000; SameSite=Lax';
 const url=new URL(location.href);url.searchParams.set('type',mode);
 if (/^\/seasons\//.test(url.pathname)) url.pathname='/seasons';
 url.searchParams.delete('season');location.assign(url);
}));
</script>
<style>
.header-actions{display:flex;align-items:center;gap:8px;margin-left:auto}
.header-theme-toggle{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto}
@media(min-width:901px){.header-actions{order:3}.main-nav{order:2}.header-theme-toggle{margin-left:6px}}
</style>
@stack('scripts')
</body>
</html>