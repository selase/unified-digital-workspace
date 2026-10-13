@extends('errors::custom-layout')

@section('title', __('This page has expired'))

@section('code', '419')

@section('message', __('The page was open for too long, so for your security it timed out. Go back, refresh the page and try again.'))

@section('actions')
    <a href="javascript:history.back()" class="kt-btn kt-btn-primary">{{ __('Go back') }}</a>
@endsection
