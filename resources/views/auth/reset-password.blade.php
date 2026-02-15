@extends('layouts.metronic.auth')

@section('title', 'Setup New Password')

@section('content')
    <form class="w-full space-y-6" novalidate="novalidate" id="newPasswordForm" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Setup New Password</h1>
            <div class="text-sm text-muted-foreground">Already reset your password?
                <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Sign in here</a>
            </div>

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
                <input class="kt-input" type="email" name="email" value="{{ old('email', $request->email) }}" autocomplete="email" required />
            </div>
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Password</label>
            <div class="kt-form-control">
                <input class="kt-input" type="password" name="password" autocomplete="new-password" required />
            </div>
            <p class="mt-2 text-xs text-muted-foreground">Use 8 or more characters with a mix of letters, numbers, and symbols.</p>
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Confirm Password</label>
            <div class="kt-form-control">
                <input class="kt-input" type="password" name="password_confirmation" autocomplete="new-password" required />
            </div>
        </div>

        <div>
            <button type="submit" id="newPasswordButton" class="kt-btn kt-btn-primary w-full">
                {{ __('Reset Password') }}
            </button>
        </div>
    </form>
@endsection
