<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ECFHL</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f5f6f8; color: #14171a; }
        main { max-width: 960px; margin: 80px auto; padding: 32px; background: #fff; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,.08); }
        h1 { margin-top: 0; }
        .status { display: inline-block; padding: 7px 11px; border-radius: 999px; font-weight: 700; }
        .ok { background: #e8f5e9; color: #176b2c; }
        .bad { background: #fdecec; color: #9d1c1c; }
        .details { margin-top: 24px; padding: 18px; background: #f8f9fb; border-radius: 8px; }
        code { font-family: Consolas, monospace; }
    </style>
</head>
<body>
<main>
    <h1>ECFHL</h1>
    <p>East Coast Fantasy Hockey League history.</p>

    @if ($databaseConnected)
        <p class="status ok">MySQL connected</p>
        <div class="details">
            <div><strong>Database:</strong> <code>{{ $databaseName }}</code></div>
            <div><strong>MySQL version:</strong> <code>{{ $databaseVersion }}</code></div>
            <div><strong>Application:</strong> Laravel 12</div>
        </div>
    @else
        <p class="status bad">MySQL connection unavailable</p>
    @endif

    <p style="margin-top:24px;">The database-backed ECFHL rebuild is now running separately from the existing ecfhl.win site.</p>
</main>
</body>
</html>
