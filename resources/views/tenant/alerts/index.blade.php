@extends('layouts.metronic.app')

@section('title', 'Alerts')

@section('content')
    <section class="grid gap-6">
        <div class="kt-card p-6 lg:p-8">
            <p class="text-xs uppercase tracking-wide text-muted-foreground">Settings</p>
            <h1 class="mt-2 text-2xl font-semibold text-foreground">Alerts</h1>
            <p class="mt-2 text-sm text-muted-foreground">Choose what you want to be told about and how. Organisation administrators are always alerted; add extra emails or phone numbers if someone else should know too.</p>
        </div>

        @if ($errors->any())
            <div class="rounded-lg bg-destructive/10 p-4 text-sm text-destructive">
                @foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Alert channels</h2>
            <p class="mt-1 text-sm text-muted-foreground">Switch a channel off to stop every alert using it. Text messages use your SMS credits.</p>
            <form action="{{ route('tenant.settings.alerts.channels') }}" method="POST" class="mt-4 flex flex-wrap items-center gap-6">
                @csrf
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="mail" value="1" @checked($enabledChannels['mail'])> Email</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sms" value="1" @checked($enabledChannels['sms'])> SMS</label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="database" value="1" @checked($enabledChannels['database'])> In-app notifications</label>
                <button type="submit" class="kt-btn kt-btn-primary">Save channels</button>
            </form>
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">New alert</h2>
            <form action="{{ route('tenant.settings.alerts.store') }}" method="POST" class="mt-4 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="event">When this happens</label>
                    <select id="event" name="event" class="kt-select mt-1 w-full" required>
                        @foreach ($events as $key => $event)
                            <option value="{{ $key }}" @selected(old('event') === $key)>{{ $event['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="name">Name</label>
                    <input id="name" name="name" class="kt-input mt-1 w-full" value="{{ old('name') }}" placeholder="e.g. Tell the finance team" required>
                </div>
                <div class="md:col-span-2">
                    <p class="text-xs font-semibold text-muted-foreground">Alert me by</p>
                    <div class="mt-2 flex flex-wrap gap-6">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channels[]" value="mail" @checked(in_array('mail', old('channels', ['mail'])))> Email</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channels[]" value="sms" @checked(in_array('sms', old('channels', [])))> SMS</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="channels[]" value="database" @checked(in_array('database', old('channels', [])))> In-app</label>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="emails">Also email (optional, separate with commas)</label>
                    <textarea id="emails" name="emails" rows="2" class="kt-input mt-1 w-full">{{ is_array(old('emails')) ? implode(', ', old('emails')) : old('emails') }}</textarea>
                </div>
                <div>
                    <label class="text-xs font-semibold text-muted-foreground" for="phones">Also text (optional, separate with commas)</label>
                    <textarea id="phones" name="phones" rows="2" class="kt-input mt-1 w-full">{{ is_array(old('phones')) ? implode(', ', old('phones')) : old('phones') }}</textarea>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="kt-btn kt-btn-primary">Create alert</button>
                </div>
            </form>
        </div>

        <div class="kt-card p-6">
            <h2 class="text-lg font-semibold text-foreground">Your alerts</h2>
            <div class="mt-4 grid gap-3">
                @forelse ($rules as $rule)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg bg-muted/30 p-4">
                        <div>
                            <p class="font-semibold text-foreground">{{ $rule->name }} <span class="text-xs font-normal text-muted-foreground">{{ $rule->is_active ? '' : '(off)' }}</span></p>
                            <p class="text-xs text-muted-foreground">{{ $events[$rule->event]['label'] ?? $rule->event }} · by {{ implode(', ', array_map(fn ($c) => ['mail' => 'email', 'sms' => 'SMS', 'database' => 'in-app'][$c] ?? $c, $rule->channels)) }}</p>
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('tenant.settings.alerts.toggle', $rule) }}" method="POST">@csrf @method('PATCH')
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">{{ $rule->is_active ? 'Switch off' : 'Switch on' }}</button>
                            </form>
                            <form action="{{ route('tenant.settings.alerts.destroy', $rule) }}" method="POST">@csrf @method('DELETE')
                                <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">Delete</button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No alerts yet. Create one above.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
