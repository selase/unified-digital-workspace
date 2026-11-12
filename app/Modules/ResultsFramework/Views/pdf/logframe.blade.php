<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $programme->name }} logframe</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2937; }
        h1 { font-size: 17px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; vertical-align: top; padding: 5px; border-bottom: 1px solid #e5e7eb; overflow-wrap: break-word; }
        thead th { background: #f3f4f6; }
    </style>
</head>
<body>
    <h1>{{ $programme->name }} logframe</h1>
    @include('results-framework::programmes._logframe_table')
</body>
</html>
