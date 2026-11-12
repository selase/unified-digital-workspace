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
<div class="kt-card">
    <div class="kt-card-header"><h2 class="kt-card-title">Results chain</h2></div>
    <div class="kt-card-content">
        @foreach($errors->all() as $error)
            <p class="text-destructive" role="alert">{{ $error }}</p>
        @endforeach
        @include('results-framework::programmes._chain', ['nodes' => $chain])
        @can('results.manage')
            <form method="POST" action="{{ route('results-framework.programmes.results.store', $programme->uuid) }}" class="flex flex-col gap-3">
                @csrf
                <label>Level <select class="kt-select" name="level">@foreach(['goal', 'outcome', 'output', 'activity'] as $level)<option value="{{ $level }}" @selected(old('level') === $level)>{{ ucfirst($level) }}</option>@endforeach</select></label>
                <label>Title <input class="kt-input" name="title" maxlength="255" required value="{{ old('title') }}"></label>
                <label>Parent <select class="kt-select" name="parent_id"><option value="">Top level</option>@foreach($resultOptions as $option)<option value="{{ $option->id }}" @selected((string) old('parent_id') === (string) $option->id)>{{ $option->title }}</option>@endforeach</select></label>
                <label>Position <input class="kt-input" type="number" min="0" name="sort_order" value="{{ old('sort_order', 0) }}"></label>
                <label>Description <textarea class="kt-input" name="description">{{ old('description') }}</textarea></label>
                <label>Assumptions <textarea class="kt-input" name="assumptions">{{ old('assumptions') }}</textarea></label>
                <label>Risks <textarea class="kt-input" name="risks">{{ old('risks') }}</textarea></label>
                @if($projects->isNotEmpty())
                    <label>Project (activities only) <select class="kt-select" name="project_id"><option value="">None</option>@foreach($projects as $project)<option value="{{ $project->id }}" @selected((string) old('project_id') === (string) $project->id)>{{ $project->name }}</option>@endforeach</select></label>
                @endif
                <div><button class="kt-btn kt-btn-primary">Add result</button></div>
            </form>
        @endcan
    </div>
</div>
@endsection
