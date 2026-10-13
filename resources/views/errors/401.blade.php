@extends('errors::custom-layout')

@section('title', __('Please sign in'))

@section('code', '401')

@section('message', __('You need to be signed in to see this page.'))

@section('actions')
    <a href="{{ Route::has('login') ? route('login') : '/' }}" class="kt-btn kt-btn-primary">{{ __('Sign in') }}</a>
@endsection
