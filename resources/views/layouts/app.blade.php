<!doctype html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'ECFHL History')</title>
    <link rel="stylesheet" href="/app.css?v=1">
</head>
<body>
<header class="site-header">
    <div class="shell nav-wrap">
        <a class="brand" href="/">
            <span class="brand-mark">ECFHL</span>
            <span class="brand-copy"><strong>ECFHL</strong><small>League History</small></span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Toggle navigation" onclick="document.body.classList.toggle('nav-open')">☰</button>
        <nav class="main-nav">
            @foreach ([
                '/' => 'Overview',
                '/seasons' => 'Seasons',
                '/teams' => 'Teams',
                '/prizes' => 'Prizes',
                '/trades' => 'Trades',
                '/draft' => 'Draft',
                '/rules' => 'Rules',
            ] as $url => $label)
                <a href="{{ $url }}" class="{{ request()->is(ltrim($url,'/')) || ($url==='/' && request()->is('/')) ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
            <button class="theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch theme">◐</button>
        </nav>
    </div>
</header>

<main>
    @yield('content')
</main>

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
@stack('scripts')
</body>
</html>
