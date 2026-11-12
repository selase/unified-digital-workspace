@extends('layouts.metronic.app')
@section('title', $programme->exists ? 'Edit programme' : 'New programme')
@section('content')
<form class="kt-card" method="POST" action="{{ $programme->exists ? route('results-framework.programmes.update', $programme->uuid) : route('results-framework.programmes.store') }}">
    @csrf
    @if($programme->exists)
        @method('PUT')
    @endif
    <div class="kt-card-header"><h1 class="kt-card-title">{{ $programme->exists ? 'Edit programme' : 'New programme' }}</h1></div>
    <div class="kt-card-content flex flex-col gap-3">
        @foreach($errors->all() as $error)
            <p class="text-destructive" role="alert">{{ $error }}</p>
        @endforeach
        <label>Name <input class="kt-input" name="name" required maxlength="255" value="{{ old('name', $programme->name) }}"></label>
        <label>Description <textarea class="kt-input" name="description">{{ old('description', $programme->description) }}</textarea></label>
        <label>Status <select class="kt-select" name="status">@foreach($programme::STATUSES as $status)<option @selected(old('status', $programme->status) === $status) value="{{ $status }}">{{ ucfirst($status) }}</option>@endforeach</select></label>
        <label>Start date <input class="kt-input" type="date" name="starts_on" value="{{ old('starts_on', $programme->starts_on?->toDateString()) }}"></label>
        <label>End date <input class="kt-input" type="date" name="ends_on" value="{{ old('ends_on', $programme->ends_on?->toDateString()) }}"></label>
        @if($grants !== null)
            <label>Grant
                <select class="kt-select" name="grant_id">
                    <option value="">No grant</option>
                    @if($programme->grant_id && ! $grants->contains('id', $programme->grant_id))
                        <option value="{{ $programme->grant_id }}" @selected(old('grant_id', $programme->grant_id) == $programme->grant_id)>Previously linked grant (unavailable)</option>
                    @endif
                    @foreach($grants as $grant)
                        <option value="{{ $grant->id }}" @selected((string) old('grant_id', $programme->grant_id) === (string) $grant->id)>{{ $grant->title }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <div><button class="kt-btn kt-btn-primary">Save programme</button></div>
    </div>
</form>
@endsection
