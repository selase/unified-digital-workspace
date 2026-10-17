@extends('layouts.metronic.app')

@section('title', 'Categories')

@section('content')
<div class="flex flex-col gap-6">
    {{-- Page Header --}}
    <x-metronic.page-header
        title="Categories"
        description="Organize posts with hierarchical categories and structured taxonomies across all portals."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">
                Back to Hub
            </a>
            <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.posts.index') }}">
                Post Library
            </a>
            @can('cms.categories.manage')
                <a class="kt-btn kt-btn-primary flex items-center gap-1.5" href="{{ route('cms-core.categories.create') }}">
                    <i class="ki-filled ki-plus text-base"></i>
                    New Category
                </a>
            @endcan
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('message'))
        <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-emerald-800 dark:text-emerald-300 text-sm flex items-center gap-2">
            <svg class="h-5 w-5 shrink-0 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            {{ session('message') }}
        </div>
    @endif

    {{-- Categories Card --}}
    <div class="kt-card kt-card-grid min-w-full">
        <div class="kt-card-header py-5 flex-wrap gap-3">
            <div class="flex items-center gap-2.5">
                <h3 class="kt-card-title text-base font-semibold">All Categories</h3>
                <span class="kt-badge kt-badge-outline font-medium">{{ $categories->total() }} total</span>
            </div>
            <form class="flex w-full lg:w-auto items-center gap-2" method="GET" action="{{ route('cms-core.categories.index') }}">
                <div class="relative w-full sm:w-[260px]">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-muted-foreground">
                        <i class="ki-filled ki-magnifier"></i>
                    </span>
                    <input class="kt-input w-full pl-9" name="search" placeholder="Search categories" type="text" value="{{ $search }}" />
                </div>
                <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                @if($search !== '')
                    <a href="{{ route('cms-core.categories.index') }}" class="kt-btn kt-btn-link text-xs text-muted-foreground hover:text-foreground">Clear</a>
                @endif
            </form>
        </div>
        <div class="kt-card-content p-0">
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border align-middle w-full text-sm">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground border-b border-border bg-muted/20">
                            <th class="py-3 px-4 font-semibold text-start min-w-[200px]">Name</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[180px]">Slug</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[100px]">Posts</th>
                            <th class="py-3 px-4 font-semibold text-start min-w-[120px]">Status</th>
                            <th class="py-3 px-4 font-semibold text-end min-w-[140px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-foreground">
                        @forelse($categories as $category)
                            <tr class="hover:bg-muted/10 transition-colors">
                                <td class="py-3 px-4 font-medium text-foreground">
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                                            </svg>
                                        </div>
                                        <span>{{ $category->name }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-mono text-xs text-muted-foreground">{{ $category->slug }}</td>
                                <td class="py-3 px-4">
                                    <span class="kt-badge kt-badge-outline text-xs">
                                        {{ $category->posts_count }}
                                    </span>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="kt-badge {{ $category->is_active ? 'kt-badge-success' : 'kt-badge-outline' }} text-xs">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-end">
                                    @can('cms.categories.manage')
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a class="kt-btn kt-btn-xs kt-btn-outline" href="{{ route('cms-core.categories.edit', $category) }}">Edit</a>
                                            <form action="{{ route('cms-core.categories.destroy', $category) }}" method="POST" onsubmit="return confirm('Delete this category?')" class="inline-block">
                                                @csrf
                                                @method('DELETE')
                                                <button class="kt-btn kt-btn-xs kt-btn-outline text-destructive hover:bg-destructive/10" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-10 text-center text-muted-foreground" colspan="5">No categories found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($categories->hasPages())
                <div class="p-4 border-t border-border">
                    {{ $categories->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
