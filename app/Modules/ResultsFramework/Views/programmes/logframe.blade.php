@extends('layouts.metronic.app')
@section('title', 'Logframe · '.$programme->name)
@section('content')
<div class="kt-card">
    <div class="kt-card-header flex flex-wrap gap-2">
        <h1 class="kt-card-title">{{ $programme->name }} logframe</h1>
        <div class="flex flex-wrap gap-2">
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.programmes.show', $programme->uuid) }}">Back to programme</a>
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.programmes.logframe', [$programme->uuid, 'format' => 'csv']) }}">Download CSV</a>
            <a class="kt-btn kt-btn-outline" href="{{ route('results-framework.programmes.logframe', [$programme->uuid, 'format' => 'pdf']) }}">Download PDF</a>
        </div>
    </div>
    <div class="kt-card-table kt-scrollable-x-auto">
        @include('results-framework::programmes._logframe_table')
    </div>
</div>
@endsection
