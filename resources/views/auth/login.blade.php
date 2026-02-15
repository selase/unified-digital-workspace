@extends('layouts.metronic.auth')

@section('title', 'Login')

@section('content')
    <form class="w-full space-y-6" novalidate="novalidate" id="signInForm" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Sign In to {{ config('app.name') }}</h1>
            <p class="text-sm text-muted-foreground">Enter your account credentials to continue.</p>

            @if (config('app.system_setting.allow_registration'))
                <div class="text-sm text-muted-foreground">
                    New Here?
                    <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">Create an Account</a>
                </div>
            @endif

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
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Email</label>
            <div class="kt-form-control">
                <input class="kt-input" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required />
            </div>
        </div>

        <div class="kt-form-item">
            <div class="mb-2 flex items-center justify-between">
                <label class="kt-form-label mb-0">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">{{ __('Forgot Password') }}?</a>
                @endif
            </div>
            <div class="kt-form-control">
                <input class="kt-input" type="password" name="password" autocomplete="current-password" required />
            </div>
        </div>

        <div class="flex items-center gap-2">
            <input class="kt-checkbox" type="checkbox" name="remember" id="remember_me" value="1" />
            <label for="remember_me" class="text-sm text-muted-foreground">{{ __('Remember me') }}</label>
        </div>

        <div class="space-y-3">
            <button type="submit" id="signInButton" class="kt-btn kt-btn-primary w-full">
                Continue
            </button>

            @if (config('app.system_setting.enable_social_login'))
                <div class="text-center text-xs uppercase tracking-wide text-muted-foreground">or</div>

                <a href="javascript:void(0)" class="kt-btn kt-btn-outline w-full justify-center">
                    <img alt="Google logo" src="{{ asset('assets/media/svg/brand-logos/google-icon.svg') }}" class="h-5 w-5 me-2" />
                    Continue with Google
                </a>

                <a href="javascript:void(0)" class="kt-btn kt-btn-outline w-full justify-center">
                    <img alt="Facebook logo" src="{{ asset('assets/media/svg/brand-logos/facebook-4.svg') }}" class="h-5 w-5 me-2" />
                    Continue with Facebook
                </a>

                <a href="javascript:void(0)" class="kt-btn kt-btn-outline w-full justify-center">
                    <img alt="Apple logo" src="{{ asset('assets/media/svg/brand-logos/apple-black.svg') }}" class="h-5 w-5 me-2" />
                    Continue with Apple
                </a>
            @endif
        </div>
    </form>
@endsection
