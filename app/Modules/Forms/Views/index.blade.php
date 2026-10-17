@extends('layouts.metronic.app')

@section('title', 'Forms & Surveys Hub')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Forms, HR Requests & Surveys"
        description="Build institutional request forms, HR employee requisitions, feedback surveys, and live community polls."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-primary" href="{{ route('forms.create') }}">
                + Create New Form
            </a>
        </x-slot:actions>
    </x-metronic.page-header>

    {{-- Metrics --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Total Forms</div>
            <div class="text-2xl font-extrabold text-foreground mt-1">{{ number_format($metrics['total_forms']) }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Published Forms</div>
            <div class="text-2xl font-extrabold text-primary mt-1">{{ number_format($metrics['published_forms']) }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Total Submissions</div>
            <div class="text-2xl font-extrabold text-success mt-1">{{ number_format($metrics['total_submissions']) }}</div>
        </div>
        <div class="kt-card p-4">
            <div class="text-xs uppercase font-semibold text-muted-foreground">Pending Review</div>
            <div class="text-2xl font-extrabold text-warning mt-1">{{ number_format($metrics['pending_reviews']) }}</div>
        </div>
    </div>

    {{-- Forms Directory --}}
    <div class="kt-card p-6">
        <div class="flex flex-wrap items-center justify-between gap-4 mb-6">
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('forms.index') }}" class="kt-btn kt-btn-xs {{ empty($currentType) ? 'kt-btn-primary' : 'kt-btn-outline' }}">All Forms</a>
                <a href="{{ route('forms.index', ['type' => 'general']) }}" class="kt-btn kt-btn-xs {{ $currentType === 'general' ? 'kt-btn-primary' : 'kt-btn-outline' }}">General</a>
                <a href="{{ route('forms.index', ['type' => 'hr_request']) }}" class="kt-btn kt-btn-xs {{ $currentType === 'hr_request' ? 'kt-btn-primary' : 'kt-btn-outline' }}">HR Requisitions</a>
                <a href="{{ route('forms.index', ['type' => 'survey']) }}" class="kt-btn kt-btn-xs {{ $currentType === 'survey' ? 'kt-btn-primary' : 'kt-btn-outline' }}">Surveys</a>
                <a href="{{ route('forms.index', ['type' => 'live_poll']) }}" class="kt-btn kt-btn-xs {{ $currentType === 'live_poll' ? 'kt-btn-primary' : 'kt-btn-outline' }}">Live Polls</a>
            </div>

            <form method="GET" action="{{ route('forms.index') }}" class="flex items-center gap-2">
                @if($currentType) <input type="hidden" name="type" value="{{ $currentType }}"> @endif
                <input class="kt-input text-xs w-48" type="text" name="search" placeholder="Search forms..." value="{{ $search }}">
                <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="kt-table table-auto kt-table-border w-full text-sm">
                <thead>
                    <tr class="border-b border-border/60 text-left text-xs font-semibold text-muted-foreground">
                        <th class="py-3 px-4">Form Title & Slug</th>
                        <th class="py-3 px-4">Type</th>
                        <th class="py-3 px-4">Audience</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Responses</th>
                        <th class="py-3 px-4">Created Date</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border/40">
                    @forelse($forms as $form)
                        <tr class="hover:bg-muted/30 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('forms.show', $form) }}" class="font-bold text-foreground hover:text-primary">
                                    {{ $form->title }}
                                </a>
                                <div class="text-xs text-muted-foreground font-mono">/forms/f/{{ $form->slug }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm kt-badge-outline uppercase text-[10px]">
                                    {{ str_replace('_', ' ', $form->type) }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm {{ $form->target_audience === 'public' ? 'kt-badge-success' : 'kt-badge-primary' }}">
                                    {{ $form->target_audience }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="kt-badge kt-badge-sm {{ $form->status === 'published' ? 'kt-badge-success' : ($form->status === 'draft' ? 'kt-badge-warning' : 'kt-badge-destructive') }}">
                                    {{ $form->status }}
                                </span>
                            </td>
                            <td class="py-3 px-4 font-semibold text-foreground">
                                {{ $form->type === 'live_poll' ? $form->poll_votes_count : $form->submissions_count }}
                            </td>
                            <td class="py-3 px-4 text-xs text-muted-foreground">
                                {{ $form->created_at?->format('d/m/Y') }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('forms.public.show', $form->slug) }}" target="_blank" class="kt-btn kt-btn-xs kt-btn-outline" title="Open Public View">
                                        Open Form
                                    </a>
                                    <a href="{{ route('forms.show', $form) }}" class="kt-btn kt-btn-xs kt-btn-primary">
                                        Manage
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-muted-foreground">
                                No forms registered yet. Click "+ Create New Form" to build your first institutional intake form.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $forms->links() }}
        </div>
    </div>
</div>
@endsection

