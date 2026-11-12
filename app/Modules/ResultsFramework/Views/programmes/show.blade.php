@extends('layouts.metronic.app')
@section('title', $programme->name)
@section('content')
@include('results-framework::partials.flash')
<div class="kt-card">
    <div class="kt-card-header">
        <h1 class="kt-card-title">{{ $programme->name }}</h1>
        @can('results.manage')
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.programmes.edit', $programme->uuid) }}">Edit programme</a>
            @if($programme->status !== 'closed')
                <form method="POST" action="{{ route('results-framework.programmes.close', $programme->uuid) }}">
                    @csrf
                    <button class="kt-btn kt-btn-outline">Close programme</button>
                </form>
            @endif
        @endcan
    </div>
    <div class="kt-card-content">
        <p>{{ ucfirst($programme->status) }}</p>
        <p>{{ $programme->description }}</p>
        <h2>Indicators</h2>
        @forelse($programme->indicators as $indicator)
            <p><a class="text-primary" href="{{ route('results-framework.indicators.show', $indicator->uuid) }}">{{ $indicator->name }}</a></p>
        @empty
            <p>No indicators are linked to this programme.</p>
        @endforelse
    </div>
</div>
@endsection
