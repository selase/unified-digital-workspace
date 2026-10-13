@extends('errors::custom-layout')

@section('title', __('We\'ll be right back'))

@section('code', '503')

@section('message', __('The site is briefly unavailable while we carry out maintenance. Please try again in a few minutes.'))

@section('actions')
    <a href="javascript:location.reload()" class="kt-btn kt-btn-primary">{{ __('Try again') }}</a>
@endsection
