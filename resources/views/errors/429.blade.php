@extends('errors::custom-layout')

@section('title', __('Too many requests'))

@section('code', '429')

@section('message', __('You\'ve done that too many times in a short while. Please wait a minute and try again.'))

@section('actions')
    <a href="javascript:history.back()" class="kt-btn kt-btn-primary">{{ __('Go back') }}</a>
@endsection
