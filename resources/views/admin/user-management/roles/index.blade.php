@extends('layouts.metronic.app')

@section('title', __('locale.labels.roles'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Access Control</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('locale.labels.roles') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Create and manage role definitions for the platform.</p>
                </div>
                <a href="{{ route('roles.create') }}" class="kt-btn kt-btn-primary">
                    <i class="ki-filled ki-plus text-base"></i>
                    Add New Role
                </a>
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($roles as $role)
                <article class="rounded-xl border border-border bg-background p-6 flex flex-col gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-foreground">{{ $role->name }}</h2>
                        <p class="text-sm text-muted-foreground mt-1">Total users with this role: {{ $role->users_count ?? 0 }}</p>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Permissions</div>
                        <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                            @foreach ($role->permissions->slice(0, 5) as $permission)
                                <li class="flex items-center gap-2">
                                    <span class="size-2 rounded-full bg-primary"></span>
                                    <span>{{ $permission->name }}</span>
                                </li>
                            @endforeach
                            @if($role->permissions->isEmpty())
                                <li class="text-sm text-muted-foreground">No specific permissions assigned.</li>
                            @endif
                        </ul>
                    </div>
                    <div class="flex flex-wrap items-center gap-2 pt-2 mt-auto border-t border-border">
                        <a href="{{ route('roles.edit', $role->id) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit Role</a>
                    </div>
                </article>
            @empty
                <div class="rounded-xl border border-border bg-background p-10 flex flex-col items-center text-center md:col-span-2 xl:col-span-3">
                    <img src="{{ asset('assets/media/illustrations/sketchy-1/17.png') }}" class="w-40 mb-6" />
                    <h3 class="text-lg font-semibold text-foreground">No Roles Defined</h3>
                    <p class="text-sm text-muted-foreground">Create roles to manage permissions for your team members.</p>
                    <a href="{{ route('roles.create') }}" class="mt-4 kt-btn kt-btn-primary">Add New Role</a>
                </div>
            @endforelse
        </div>
    </section>
@endsection
