@extends('layouts.metronic.app')

@section('title', 'Modules')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Capabilities</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Modules</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Enable optional modules to extend your workspace. Locked modules require an upgrade to your plan.</p>
                </div>
            </div>
        </div>

        @if(session('status') === 'success')
            <div class="rounded-lg bg-success/10 px-4 py-3 text-sm text-success">{{ session('message') }}</div>
        @elseif(session('status') === 'error')
            <div class="rounded-lg bg-destructive/10 px-4 py-3 text-sm text-destructive">{{ session('message') }}</div>
        @endif

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($modules as $module)
                <div class="kt-card flex flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-semibold text-foreground">{{ $module['name'] }}</h2>
                            <p class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">{{ $module['tier'] }}</p>
                        </div>
                        @php
                            $badge = match($module['status']) {
                                'enabled' => ['Enabled', 'bg-success/10 text-success'],
                                'available' => ['Available', 'bg-primary/10 text-primary'],
                                'locked' => ['Locked', 'bg-muted text-muted-foreground'],
                            };
                        @endphp
                        <span class="rounded-md px-2 py-1 text-xs font-medium {{ $badge[1] }}">{{ $badge[0] }}</span>
                    </div>

                    <p class="text-sm text-muted-foreground">{{ $module['description'] ?: 'No description provided.' }}</p>

                    @if($module['status'] === 'locked' && !empty($module['missing_features']))
                        <div class="rounded-md bg-muted/30 p-3 text-xs text-muted-foreground">
                            <strong class="text-foreground">Requires:</strong> {{ implode(', ', $module['missing_features']) }}
                            <br><span class="text-muted-foreground">Upgrade your plan to unlock.</span>
                        </div>
                    @endif

                    @if(!empty($module['depends_on']))
                        <p class="text-xs text-muted-foreground"><strong>Depends on:</strong> {{ implode(', ', $module['depends_on']) }}</p>
                    @endif

                    <div class="mt-auto">
                        @if($module['status'] === 'enabled')
                            <form method="POST" action="{{ route('tenant.modules.disable', ['subdomain' => request()->route('subdomain'), 'slug' => $module['slug']]) }}">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-outline w-full">Disable</button>
                            </form>
                        @elseif($module['status'] === 'available')
                            <form method="POST" action="{{ route('tenant.modules.enable', ['subdomain' => request()->route('subdomain'), 'slug' => $module['slug']]) }}">
                                @csrf
                                <button type="submit" class="kt-btn kt-btn-primary w-full">Enable</button>
                            </form>
                        @else
                            <button type="button" class="kt-btn kt-btn-outline w-full" disabled>Locked</button>
                        @endif
                    </div>
                </div>
            @endforeach

            @if($modules->isEmpty())
                <div class="kt-card p-6 text-center text-sm text-muted-foreground md:col-span-2 xl:col-span-3">
                    No optional modules are available right now.
                </div>
            @endif
        </div>
    </section>
@endsection
