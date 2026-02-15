@extends('layouts.metronic.auth')

@section('title', '2FA Challenge')

@section('content')
    <form class="w-full space-y-6" method="POST" action="{{ route('two-factor.challenge.store') }}">
        @csrf
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-foreground">Two-Factor Authentication</h1>
            <p class="text-sm text-muted-foreground">
                Please enter the 6-digit code from your authenticator app to complete the login process.
            </p>
            
            @if ($errors->any())
                <div class="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2">
                    <ul class="text-sm text-destructive">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="kt-form-item">
            <label class="kt-form-label">Authentication Code</label>
            <div class="kt-form-control">
                <input class="kt-input text-center tracking-[0.35em]" 
                type="text" 
                name="one_time_password" 
                inputmode="numeric" 
                pattern="[0-9]*" 
                autocomplete="one-time-code" 
                placeholder="000000" 
                autofocus />
            </div>
        </div>

        <div class="space-y-3">
            <button type="submit" class="kt-btn kt-btn-primary w-full">
                Verify Code
            </button>
            <a href="{{ route('logout') }}" 
               onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
               class="inline-flex text-sm font-medium text-primary hover:underline">Cancel and Sign Out</a>
        </div>
    </form>

    <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">
        @csrf
    </form>
@endsection
