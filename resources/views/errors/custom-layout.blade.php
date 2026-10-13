<!DOCTYPE html>
<html lang="en">
<head>
    <title>@yield('title') | {{ config('app.name') }}</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex" />
    @include('partials.favicon')
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" />
    <link href="{{ asset('assets/metronic/css/styles.css') }}" rel="stylesheet" />
    {{-- Layout rules are inlined so the page still reads well if the stylesheet above fails to load. --}}
    <style>
        .error-page { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2rem; padding: 3rem 1.5rem; background: var(--muted, #f4f4f5); color: var(--foreground, #18181b); font-family: Inter, system-ui, sans-serif; text-align: center; }
        .error-card { width: 100%; max-width: 30rem; padding: 2.5rem 2rem; border-radius: 1rem; background: var(--background, #fff); box-shadow: 0 1px 2px rgb(0 0 0 / 0.05), 0 8px 24px rgb(0 0 0 / 0.06); }
        .error-code { margin: 0; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.08em; text-transform: uppercase; color: var(--primary, #2563eb); }
        .error-title { margin: 0.75rem 0 0; font-size: 1.625rem; font-weight: 600; line-height: 1.25; letter-spacing: -0.01em; }
        .error-message { margin: 0.875rem auto 0; max-width: 24rem; font-size: 0.9375rem; line-height: 1.6; color: var(--muted-foreground, #71717a); }
        .error-actions { margin-top: 1.75rem; display: flex; flex-wrap: wrap; justify-content: center; gap: 0.75rem; }
        .error-footer { font-size: 0.75rem; color: var(--muted-foreground, #71717a); }
        .error-footer a { color: inherit; text-decoration: none; }
        .error-footer a:hover { text-decoration: underline; }
    </style>
    <script>
        (function () {
            var mode = null;
            try { mode = localStorage.getItem('kt-theme'); } catch (e) {}
            if (mode === 'dark' || (mode === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
</head>
<body class="antialiased">
    <main class="error-page">
        <a href="/" aria-label="Home">
            <x-brand-logo />
        </a>

        <section class="error-card">
            <p class="error-code">Error @yield('code')</p>
            <h1 class="error-title">@yield('title')</h1>
            <p class="error-message">@yield('message')</p>

            <div class="error-actions">
                @hasSection('actions')
                    @yield('actions')
                @else
                    <a href="javascript:history.back()" class="kt-btn kt-btn-outline">Go back</a>
                    <a href="/" class="kt-btn kt-btn-primary">Go to home page</a>
                @endif
            </div>
        </section>

        <footer class="error-footer">
            <a href="{{ config('app.system_setting.provider.url') }}" target="_blank" rel="noopener noreferrer">
                &copy; {{ \App\Libraries\Helper::getCurrentYear() }} {{ config('app.system_setting.provider.name') }}
            </a>
        </footer>
    </main>
</body>
</html>
