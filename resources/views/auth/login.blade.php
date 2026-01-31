@extends('layouts.admin.auth')

@section('title', 'Login')

@section('content')
    <form class="form w-100" novalidate="novalidate" id="signInForm" method="POST" action="{{ route('login') }}">
        @csrf
        <div class="text-center mb-10">
            <h1 class="text-dark mb-3">Sign In to {{ config('app.name') }}</h1>
            @if (config('app.system_setting.allow_registration'))
                <div class="text-gray-400 fw-bold fs-4">
                    New Here?
                    <a href="{{ route('register') }}" class="link-primary fw-bolder">Create an Account</a>
                </div>
            @endif
            @if (Session::has('status'))
                <div class="font-medium text-sm text-success mt-7">
                    {{ Session::get('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div>
                    <div class="font-medium text-danger">
                        {{ __('Whoops! Something went wrong.') }}
                    </div>

                    <ul class="mt-3 list-disc list-inside text-sm text-danger">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="fv-row mb-10">
            <label class="form-label fs-6 fw-bolder text-dark">Email</label>
            <input class="form-control form-control-lg form-control-solid" type="text" name="email" autocomplete="off" />
        </div>

        <div class="fv-row mb-10">
            <div class="d-flex flex-stack mb-2">
                <label class="form-label fw-bolder text-dark fs-6 mb-0">Password</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="link-primary fs-6 fw-bolder">{{ __('Forgot Password') }} ?</a>
                @endif
            </div>
            <input class="form-control form-control-lg form-control-solid" type="password" name="password" autocomplete="off" />
        </div>

        <div class="fv-row mb-10">
            <div class="form-check form-check-custom form-check-solid form-check-inline">
                <input class="form-check-input" type="checkbox" name="remember" id="remember_me" value="1" />
                <label for="remember_me" class="form-check-label fw-bold text-gray-700 fs-6">{{ __('Remember me') }}</label>
            </div>
        </div>

        <div class="text-center">
            <button type="submit" id="signInButton" class="btn btn-lg btn-primary w-100 mb-5">
                <span class="indicator-label">Continue</span>
                <span class="indicator-progress">Please wait...
                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>

            @if (config('app.system_setting.enable_social_login'))
                <div class="text-center text-muted text-uppercase fw-bolder mb-5">or</div>

                <a href="javascript:void(0)" class="btn btn-flex flex-center btn-light btn-lg w-100 mb-5">
                <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/google-icon.svg') }}" class="h-20px me-3" />Continue with Google</a>

                <a href="javascript:void(0)" class="btn btn-flex flex-center btn-light btn-lg w-100 mb-5">
                <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/facebook-4.svg') }}" class="h-20px me-3" />Continue with Facebook</a>

                <a href="javascript:void(0)" class="btn btn-flex flex-center btn-light btn-lg w-100">
                <img alt="Logo" src="{{ asset('assets/media/svg/brand-logos/apple-black.svg') }}" class="h-20px me-3" />Continue with Apple</a>
            @endif
        </div>
    </form>
@endsection
