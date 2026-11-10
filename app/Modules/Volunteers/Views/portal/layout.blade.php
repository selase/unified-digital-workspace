<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $profile->legal_name ?: $tenant->name }}</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; background: #f4f5f7; color: #1f2937; }
        .wrap { max-width: 640px; margin: 0 auto; padding: 28px 16px; }
        .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,.08); margin-top: 16px; }
        h1 { font-size: 22px; margin: 0 0 4px; } h2 { font-size: 16px; margin: 0 0 10px; }
        .muted { color: #6b7280; font-size: 13px; }
        label { display: block; font-size: 14px; font-weight: 600; margin-top: 12px; }
        input, select { width: 100%; box-sizing: border-box; margin-top: 6px; padding: 10px 12px; border: 0; border-radius: 8px; background: #f3f4f6; font-size: 15px; font-family: inherit; }
        .btn { margin-top: 16px; padding: 11px 18px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-size: 15px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn.light { background: #e5e7eb; color: #1f2937; }
        .bad { background: #fef2f2; color: #b91c1c; padding: 10px 14px; border-radius: 8px; margin-top: 14px; font-size: 14px; }
        .ok { background: #ecfdf5; color: #047857; padding: 10px 14px; border-radius: 8px; margin-top: 14px; font-size: 14px; }
        .stats { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; } .stat { flex: 1; min-width: 120px; background: #fff; border-radius: 10px; padding: 12px; } .stat b { display: block; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; } td { padding: 8px 0; border-bottom: 1px solid #e5e7eb; }
        .hp { position: absolute; left: -9999px; }
        form.inline { display: inline; } .row { display: flex; gap: 10px; flex-wrap: wrap; } .row > * { flex: 1; min-width: 140px; }
    </style>
</head>
<body>
<div class="wrap">
    @yield('content')
</div>
</body>
</html>
