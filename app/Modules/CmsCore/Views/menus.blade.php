@extends('layouts.metronic.app')

@section('title', 'CMS Menus')

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">CMS Core</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Menu Registry</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Navigation menu definitions and their item counts.</p>
                </div>
                <a class="kt-btn kt-btn-outline" href="{{ route('cms-core.index') }}">Back to Hub</a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="overflow-x-auto">
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
                            <td class="font-medium">{{ $menu->name }}</td>
                            <td>{{ $menu->slug }}</td>
                            <td>{{ $menu->items_count }}</td>
                            <td>{{ $menu->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-8 text-center text-muted-foreground" colspan="4">No menus found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $menus->links() }}</div>
        </div>
    </section>
@endsection
