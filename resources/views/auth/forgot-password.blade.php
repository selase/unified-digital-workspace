@extends('layouts.metronic.auth')

@section('title', 'Forgot Password')

@section('content')
    <form class="w-full space-y-6" novalidate="novalidate" id="password_reset_form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Forgot Password?</h1>
            <p class="text-sm text-muted-foreground">{{ __('locale.labels.forgot_password_description') }}</p>

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

        <div class="flex flex-wrap gap-3">
            <button type="submit" id="password_reset_submit" class="kt-btn kt-btn-primary">
                Email Password Reset Link
            </button>
            <a href="{{ route('login') }}" class="kt-btn kt-btn-outline">Cancel</a>
        </div>
    </form>
@endsection
