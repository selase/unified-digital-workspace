<!DOCTYPE html>
<html lang="en">
<head>
    <title>{{ config('app.name') }} | @yield('title')</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="shortcut icon" href="{{ asset('assets/metronic/media/app/favicon.ico') }}" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" />
    <link href="{{ asset('assets/metronic/vendors/keenicons/styles.bundle.css') }}" rel="stylesheet" />
    <link href="{{ asset('assets/metronic/css/styles.css') }}" rel="stylesheet" />
</head>
<body class="min-h-screen antialiased bg-mono text-foreground">
    <main class="mx-auto flex min-h-screen w-full max-w-5xl flex-col items-center justify-center px-6 py-12 text-center">
        <a href="/" class="mb-8 inline-flex items-center gap-3">
            <img alt="Logo" src="{{ asset('assets/metronic/media/app/default-logo.svg') }}" class="h-7 w-auto" />
            <span class="text-sm font-semibold text-foreground">{{ config('app.name') }}</span>
        </a>

        <section class="w-full rounded-2xl border border-border bg-background px-8 py-10 shadow-sm">
            <h1 class="text-3xl font-semibold tracking-tight text-foreground">
                @yield('code') @yield('title')
            </h1>
            <p class="mx-auto mt-4 max-w-2xl text-sm text-muted-foreground">
                @yield('message')
            </p>

            <div class="mt-8">
                <a href="javascript:history.go(-1)" class="kt-btn kt-btn-primary">Go back</a>
            </div>

            <div class="mt-8">
                @yield('image')
            </div>
        </section>

        <footer class="mt-8 text-xs text-muted-foreground">
            <a href="{{ config('app.system_setting.provider.url') }}" class="hover:text-primary" target="_blank" rel="noopener noreferrer">
                Copyright &copy; {{ \App\Libraries\Helper::getCurrentYear() }} {{ config('app.system_setting.provider.name') }}
            </a>
        </footer>
    </main>
</body>
</html>
