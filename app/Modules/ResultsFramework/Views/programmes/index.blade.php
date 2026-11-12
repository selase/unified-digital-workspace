@extends('layouts.metronic.app')
@section('title', 'Programmes')
@section('content')
<div class="kt-card">
    <div class="kt-card-header">
        <h1 class="kt-card-title">Programmes</h1>
        @can('results.manage')
            <a class="kt-btn kt-btn-primary" href="{{ route('results-framework.programmes.create') }}">New programme</a>
        @endcan
    </div>
    <div class="kt-card-content">
        @forelse($programmes as $programme)
            <p><a class="text-primary" href="{{ route('results-framework.programmes.show', $programme->uuid) }}">{{ $programme->name }}</a> · {{ ucfirst($programme->status) }} · {{ $programme->indicators_count }} indicators</p>
        @empty
            <p>No programmes yet.</p>
        @endforelse
    </div>
</div>
@endsection
