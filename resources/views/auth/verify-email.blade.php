@extends('layouts.metronic.auth')

@section('title', 'Verify Email')

@section('content')
    <div class="w-full space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Verify Your Email</h1>
            <p class="text-sm text-muted-foreground">
                {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </p>

            @if (session('status') == 'verification-link-sent')
                <div class="rounded-lg border border-success/30 bg-success/10 px-3 py-2 text-sm text-success">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif
        </div>

        <div class="flex flex-wrap gap-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="kt-btn kt-btn-primary">
                    {{ __('Resend Verification Email') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="kt-btn kt-btn-outline">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
@endsection
