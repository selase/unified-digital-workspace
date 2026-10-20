@extends('layouts.metronic.app')

@section('title', 'Module Help')

@section('content')
    <x-metronic.page-header title="Module Help" subtitle="Getting started, everyday tasks and troubleshooting." />
    <div class="kt-card p-6 lg:p-8">
        @include('help.content')
    </div>
@endsection
