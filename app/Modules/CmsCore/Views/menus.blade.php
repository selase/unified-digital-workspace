@extends('layouts.metronic.app')

@section('title', 'CMS Menus')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Menu Registry</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Navigation menu definitions and their item counts.</p>
                </div>
                <div class="flex gap-2">
                    @can('cms.menus.manage')
                        <a class="kt-btn kt-btn-primary" href="{{ route('cms-core.menus.create') }}">New Menu</a>
                    @endcan
                    <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
                </div>
            </div>
        </div>

        <div class="kt-card kt-card-grid min-w-full">
            <div class="kt-card-header flex-wrap gap-3">
                <h3 class="kt-card-title text-sm">Menu Registry</h3>
                <form class="flex flex-wrap items-center gap-2" method="GET" action="{{ route('cms-core.menus.index') }}">
                    <input
                        class="kt-input w-full min-w-[220px] lg:w-[280px]"
                        name="search"
                        placeholder="Search menu name"
                        type="text"
                        value="{{ $search }}"
                    />
                    <button class="kt-btn kt-btn-outline" type="submit">Filter</button>
                </form>
            </div>
            <div class="kt-card-content">
                <div class="kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr class="text-xs uppercase text-muted-foreground">
                                <th>Name</th>
                                <th>Slug</th>
                                <th>Items</th>
                                <th>Updated</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-foreground">
                            @forelse($menus as $menu)
                                <tr>
                                    <td>
                                        <a href="{{ route('cms-core.menus.edit', $menu) }}" class="font-medium hover:underline">{{ $menu->name }}</a>
                                    </td>
                                    <td>{{ $menu->slug }}</td>
                                    <td>{{ $menu->items_count }}</td>
                                    <td>{{ $menu->updated_at?->diffForHumans() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="py-8 text-center text-muted-foreground" colspan="4">No menus found for this filter.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $menus->links() }}</div>
            </div>
        </div>
    </section>
@endsection
