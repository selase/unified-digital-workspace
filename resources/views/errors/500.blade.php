@extends('errors::custom-layout')

@section('title', __('Something went wrong'))

@section('code', '500')

@section('message', __('This is a problem on our side, not something you did. Please try again in a moment. If it keeps happening, let your administrator know.'))
