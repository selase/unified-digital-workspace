@extends('errors::custom-layout')

@section('title', __('You don\'t have access to this page'))

@section('code', '403')

@php
    $forbiddenDetail = $exception->getMessage();
    $hasUsefulDetail = filled($forbiddenDetail) && ! in_array($forbiddenDetail, ['Forbidden', 'This action is unauthorized.'], true);
@endphp

@section('message', $hasUsefulDetail ? __($forbiddenDetail) : __('Your account isn\'t allowed to open this page. If you think you should have access, ask an administrator of your organisation.'))
