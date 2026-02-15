@extends('layouts.metronic.auth')

@section('title', 'Confirm Password')

@section('content')
    <form class="w-full space-y-6" novalidate="novalidate" id="confirmPasswordForm" method="POST"
        action="{{ route('password.confirm') }}">
        @csrf
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Security Check</h1>
            <p class="text-sm text-muted-foreground">
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </p>

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
            <label class="kt-form-label">Password</label>
            <div class="kt-form-control">
                <input class="kt-input" type="password" name="password" required autocomplete="current-password" />
            </div>
        </div>

        <div>
            <button type="submit" id="confirmButton" class="kt-btn kt-btn-primary w-full">
                Confirm
            </button>
        </div>
    </form>
@endsection
