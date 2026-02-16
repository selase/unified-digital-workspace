@extends('layouts.metronic.auth')

@section('title', 'Login')

@section('content')
    <form class="space-y-5" id="signInForm" method="POST" action="{{ route('login') }}" novalidate>
        @csrf

        <div class="text-center space-y-2">
            <h1 class="text-xl font-semibold text-mono">Sign in</h1>
            <div class="text-sm text-secondary-foreground">
                @if (config('app.system_setting.allow_registration'))
                    Need an account?
                    <a href="{{ route('register') }}" class="kt-link">Sign up</a>
                @else
                    Enter your account credentials to continue.
                @endif
            </div>
        </div>

        @if (Session::has('status'))
            <div class="rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success">
                {{ Session::get('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2">
                <div class="text-sm font-medium text-destructive">
                    {{ __('Whoops! Something went wrong.') }}
                </div>
                <ul class="mt-2 list-disc list-inside text-sm text-destructive">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (config('app.system_setting.enable_social_login'))
            <div class="grid grid-cols-2 gap-2.5">
                <a href="javascript:void(0)" class="kt-btn kt-btn-outline justify-center">
                    <img alt="Google logo" class="size-3.5 shrink-0" src="{{ asset('assets/metronic/media/brand-logos/google.svg') }}"/>
                    Use Google
                </a>
                <a href="javascript:void(0)" class="kt-btn kt-btn-outline justify-center">
                    <img alt="Apple logo" class="size-3.5 shrink-0 dark:hidden" src="{{ asset('assets/metronic/media/brand-logos/apple-black.svg') }}"/>
                    <img alt="Apple logo" class="size-3.5 shrink-0 light:hidden" src="{{ asset('assets/metronic/media/brand-logos/apple-white.svg') }}"/>
                    Use Apple
                </a>
            </div>
        @endif

        <div class="flex items-center gap-2">
            <span class="border-t border-border w-full"></span>
            <span class="text-xs text-muted-foreground font-medium uppercase">Or</span>
            <span class="border-t border-border w-full"></span>
        </div>

        <div class="flex flex-col gap-1">
            <label class="kt-form-label font-normal text-mono">Email</label>
            <input class="kt-input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" placeholder="email@email.com" required />
        </div>

        <div class="flex flex-col gap-1">
            <div class="flex items-center justify-between gap-1">
                <label class="kt-form-label font-normal text-mono">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm kt-link shrink-0">{{ __('Forgot Password') }}?</a>
                @endif
            </div>
            <div class="kt-input" data-kt-toggle-password="true">
                <input name="password" placeholder="Enter Password" type="password" autocomplete="current-password" required />
                <button class="kt-btn kt-btn-sm kt-btn-ghost kt-btn-icon bg-transparent! -me-1.5" data-kt-toggle-password-trigger="true" type="button">
                    <span class="kt-toggle-password-active:hidden">
                        <i class="ki-filled ki-eye text-muted-foreground"></i>
                    </span>
                    <span class="hidden kt-toggle-password-active:block">
                        <i class="ki-filled ki-eye-slash text-muted-foreground"></i>
                    </span>
                </button>
            </div>
        </div>

        <label class="kt-label">
            <input class="kt-checkbox kt-checkbox-sm" id="remember_me" name="remember" type="checkbox" value="1"/>
            <span class="kt-checkbox-label">{{ __('Remember me') }}</span>
        </label>

        <button type="submit" id="signInButton" class="kt-btn kt-btn-primary w-full justify-center">
            Sign In
        </button>
    </form>
@endsection
