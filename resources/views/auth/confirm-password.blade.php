@extends('layouts.admin.auth')

@section('title', 'Confirm Password')

@section('content')
    <form class="form w-100" novalidate="novalidate" id="confirmPasswordForm" method="POST"
        action="{{ route('password.confirm') }}">
        @csrf
        <div class="text-center mb-10">
            <h1 class="text-dark mb-3">Security Check</h1>
            <div class="text-gray-400 fw-bold fs-4">
                {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
            </div>

            @if ($errors->any())
                <div class="mt-7 text-start">
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
            <label class="form-label fs-6 fw-bolder text-dark">Password</label>
            <input class="form-control form-control-lg form-control-solid" type="password" name="password" required
                autocomplete="current-password" />
        </div>

        <div class="text-center">
            <button type="submit" id="confirmButton" class="btn btn-lg btn-primary w-100 mb-5">
                <span class="indicator-label">Confirm</span>
                <span class="indicator-progress">Please wait...
                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
            </button>
        </div>
    </form>
@endsection