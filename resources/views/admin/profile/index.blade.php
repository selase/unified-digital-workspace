@extends('layouts.metronic.app')

@section('title', __('locale.menu.profile'))

@section('content')
    <section class="grid gap-6">
        @if (session('warning'))
            <div class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                {{ session('warning') }}
            </div>
        @endif

        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-center gap-4">
                    <span class="size-16 overflow-hidden rounded-full border border-border bg-muted">
                        @if (! empty($user->photo))
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('local')->url($user->photo) }}" alt="{{ $user->displayName() }}" class="size-16 object-cover" />
                        @else
                            <img src="{{ $user->gravatar }}" alt="{{ $user->displayName() }}" class="size-16 object-cover" />
                        @endif
                    </span>
                    <div>
                        <p class="text-xs uppercase tracking-wide text-muted-foreground">Profile</p>
                        <h1 class="mt-1 text-2xl font-semibold text-foreground">{{ $user->displayName() }}</h1>
                        <p class="mt-1 text-sm text-muted-foreground">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="kt-badge kt-badge-outline kt-badge-primary">{{ $user->roles()->first()?->name ?? 'No Role' }}</span>
                    <span class="kt-badge kt-badge-outline">{{ ucfirst((string) $user->status) }}</span>
                </div>
            </div>

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/40 p-4">
                    <p class="text-xs uppercase text-muted-foreground">Login Sessions</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $loginSessions->count() }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/40 p-4">
                    <p class="text-xs uppercase text-muted-foreground">Activity Logs</p>
                    <p class="mt-1 text-xl font-semibold text-foreground">{{ $activityLogs->count() }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/40 p-4">
                    <p class="text-xs uppercase text-muted-foreground">Last Login</p>
                    <p class="mt-1 text-sm font-medium text-foreground">{{ $user->lastLogin() }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <h2 class="text-lg font-semibold text-foreground">Account Details</h2>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Account ID</p>
                    <p class="mt-1 text-sm text-foreground">{{ $user->uuid }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Phone</p>
                    <p class="mt-1 text-sm text-foreground">{{ $user->phone_no ?: 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Last Login IP</p>
                    <p class="mt-1 text-sm text-foreground">{{ $user->last_login_ip ? '::' . $user->last_login_ip : 'N/A' }}</p>
                </div>
                <div>
                    <p class="text-xs uppercase text-muted-foreground">Created</p>
                    <p class="mt-1 text-sm text-foreground">{{ $user->created_at?->format('M d, Y H:i') }}</p>
                </div>
            </div>
        </div>

        @include('admin.profile._login-sessions')
        @include('admin.profile._activity-logs')
    </section>
@endsection
