@extends('layouts.admin.auth')

@section('title', 'Verify Email')

@section('content')
    <div class="w-100">
        <div class="text-center mb-10">
            <h1 class="text-dark mb-3">Verify Your Email</h1>
            <div class="text-gray-400 fw-bold fs-4">
                {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
            </div>

            @if (session('status') == 'verification-link-sent')
                <div class="font-medium text-sm text-success mt-7">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </div>
            @endif
        </div>

        <div class="d-flex flex-stack">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="btn btn-lg btn-primary fw-bolder">
                    {{ __('Resend Verification Email') }}
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-lg btn-light-primary fw-bolder">
                    {{ __('Log Out') }}
                </button>
            </form>
        </div>
    </div>
@endsection