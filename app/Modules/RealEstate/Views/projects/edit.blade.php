@extends('layouts.metronic.app')

@section('title', 'Edit Project: ' . $project->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Edit Real Estate Enclave / Project</h1>
            <p class="text-sm text-muted-foreground">Update enclave project configuration or retire project.</p>
        </div>
        <a href="{{ route('real-estate.index') }}" class="kt-btn kt-btn-outline kt-btn-sm">
            &larr; Back to Real Estate Hub
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-lg bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-300 text-sm space-y-1">
            <div class="font-semibold">Validation Errors:</div>
            <ul class="list-disc list-inside text-xs">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="kt-card">
        <div class="kt-card-header">
            <h3 class="kt-card-title text-base font-semibold">Project Details: {{ $project->name }} ({{ $project->code }})</h3>
        </div>
        <div class="kt-card-body p-6">
            <form action="{{ route('real-estate.projects.update', $project) }}" method="POST" class="space-y-4">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Project Name *</label>
                        <input type="text" name="name" class="kt-input" value="{{ old('name', $project->name) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Project Code *</label>
                        <input type="text" name="code" class="kt-input font-mono" value="{{ old('code', $project->code) }}" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="sm:col-span-2">
                        <label class="kt-form-label text-xs">Location / Town *</label>
                        <input type="text" name="location" class="kt-input" value="{{ old('location', $project->location) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Region *</label>
                        <input type="text" name="region" class="kt-input" value="{{ old('region', $project->region) }}" required>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="kt-form-label text-xs">Total Acreage *</label>
                        <input type="number" step="0.01" min="0" name="total_acreage" class="kt-input" value="{{ old('total_acreage', $project->total_acreage) }}" required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Project Status *</label>
                        <select name="status" class="kt-select" required>
                            <option value="active" @selected(old('status', $project->status) === 'active')>Active</option>
                            <option value="planning" @selected(old('status', $project->status) === 'planning')>Planning / Pre-Launch</option>
                            <option value="sold_out" @selected(old('status', $project->status) === 'sold_out')>Sold Out</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="kt-form-label text-xs">Description</label>
                    <textarea name="description" class="kt-input" rows="3">{{ old('description', $project->description) }}</textarea>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-border">
                    <button type="submit" class="kt-btn kt-btn-primary">Update Project</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Danger Zone: Soft Delete --}}
    <div class="kt-card border-rose-500/20 bg-rose-500/5">
        <div class="kt-card-body p-6 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
            <div>
                <h4 class="text-sm font-bold text-rose-700 dark:text-rose-400">Soft-Delete Project</h4>
                <p class="text-xs text-muted-foreground mt-0.5">Archive this enclave project. All linked plots and records are preserved via soft-deletes.</p>
            </div>
            <form action="{{ route('real-estate.projects.destroy', $project) }}" method="POST" onsubmit="return confirm('Are you sure you want to soft-delete project {{ $project->name }}?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="kt-btn kt-btn-danger kt-btn-sm">
                    <i class="ki-filled ki-trash text-xs"></i>
                    Soft-Delete Project
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

