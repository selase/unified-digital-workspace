@extends('layouts.admin.auth')

@section('title', 'Forgot Password')

@section('content')
    <form class="form w-100" novalidate="novalidate" id="password_reset_form" method="POST" action="{{ route('password.email') }}">
        @csrf
        <div class="text-center mb-10">
            <h1 class="text-dark mb-3">Forgot Password ?</h1>
            <div class="text-gray-400 fw-bold fs-5">{{ __('locale.labels.forgot_password_description') }}</div>
            @if (Session::has('status'))
                <div class="font-medium text-sm text-success mt-7">
                    {{ Session::get('status') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mt-7">
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
            <label class="form-label fw-bolder text-gray-900 fs-6">Email</label>
            <input class="form-control form-control-solid" type="email" placeholder="" name="email" value="{{ old('email') }}" autocomplete="off" />
        </div>

        <div class="d-flex flex-wrap justify-content-center pb-lg-0">
            <button type="submit" id="password_reset_submit" class="btn btn-lg btn-primary fw-bolder me-4">
                <span class="indicator-label">Email Password Reset Link</span>
                <span class="indicator-progress">Please wait...
                <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
            <a href="{{ route('login') }}" class="btn btn-lg btn-light-primary fw-bolder">Cancel</a>
        </div>
    </form>
@endsection