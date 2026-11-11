@extends('layouts.metronic.app')

@section('title', 'Field entry')

@section('content')
<div class="flex flex-col gap-6" x-data="fieldEntry()" x-init="init()">
    <div><h1 class="text-xl font-bold text-foreground">Field entry</h1><p class="text-xs text-muted-foreground">Record services while you are out. With no signal, entries wait on this phone and are sent when you are back online. Use the beneficiary reference (for example BEN-00012); names are never stored on the phone.</p></div>

    @include('beneficiaries::partials.nav')

    <div class="rounded-lg p-3 text-sm" :class="online ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'" x-text="online ? 'Online' : 'Offline: entries will be saved on this phone'"></div>
    <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700" x-show="message" x-text="message" x-cloak></div>

    <form class="kt-card" @submit.prevent="add()">
        <div class="kt-card-header"><h3 class="kt-card-title">New entry</h3></div>
        <div class="kt-card-content grid grid-cols-1 md:grid-cols-4 gap-3">
            <input class="kt-input" x-model="form.reference" placeholder="Reference, e.g. BEN-00012" required autocapitalize="characters">
            <select class="kt-select" x-model="form.type" required>
                @foreach($types as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
            </select>
            <input class="kt-input" type="date" x-model="form.delivered_on" max="{{ today()->toDateString() }}" required>
            <input class="kt-input" x-model="form.description" placeholder="Details (optional)" maxlength="255">
            <div><button class="kt-btn kt-btn-primary" type="submit">Save entry</button></div>
        </div>
    </form>

    <div class="kt-card">
        <div class="kt-card-header"><h3 class="kt-card-title">Waiting to send (<span x-text="queue.length"></span>)</h3><button type="button" class="kt-btn kt-btn-sm kt-btn-outline" @click="sync()" :disabled="!queue.length || syncing">Send now</button></div>
        <div class="kt-card-content flex flex-col gap-2 text-sm">
            <template x-for="entry in queue" :key="entry.id">
                <div class="rounded-lg bg-muted/30 p-3 flex items-center justify-between gap-3">
                    <div><span class="font-medium" x-text="entry.reference"></span> <span x-text="entry.type"></span> <span class="text-xs text-muted-foreground" x-text="entry.delivered_on"></span>
                        <div class="text-xs text-destructive" x-show="entry.error" x-text="entry.error"></div></div>
                    <button type="button" class="text-xs text-destructive" @click="remove(entry.id)">remove</button>
                </div>
            </template>
            <span class="text-muted-foreground" x-show="!queue.length">Nothing waiting.</span>
        </div>
    </div>
    <div class="rounded-lg bg-green-50 p-3 text-sm text-green-700" x-show="sentCount > 0" x-text="sentCount + ' entr' + (sentCount === 1 ? 'y' : 'ies') + ' sent and recorded.'" x-cloak></div>
</div>

<script>
    function fieldEntry() {
        const KEY = 'udw-field-entries';
        return {
            online: navigator.onLine, queue: [], syncing: false, message: '', sentCount: 0,
            form: { reference: '', type: 'food', delivered_on: new Date().toISOString().slice(0, 10), description: '' },
            init() {
                try { this.queue = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { this.queue = []; }
                window.addEventListener('online', () => { this.online = true; this.sync(); });
                window.addEventListener('offline', () => { this.online = false; });
                if ('serviceWorker' in navigator) { navigator.serviceWorker.register('{{ route('beneficiaries.field.sw', [], false) }}', { scope: '/beneficiaries/field' }).catch(() => {}); }
                if (this.online && this.queue.length) { this.sync(); }
            },
            save() { localStorage.setItem(KEY, JSON.stringify(this.queue)); },
            add() {
                this.queue.push({ id: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2)), ...this.form, reference: this.form.reference.trim().toUpperCase() });
                this.save(); this.form.reference = ''; this.form.description = '';
                if (this.online) { this.sync(); }
            },
            remove(id) { this.queue = this.queue.filter(e => e.id !== id); this.save(); },
            async sync() {
                if (this.syncing || !this.queue.length) { return; }
                this.syncing = true; this.message = '';
                try {
                    const tokenResponse = await fetch('{{ route('beneficiaries.field.token', [], false) }}', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (!tokenResponse.ok) { throw new Error('Please sign in again, then press Send now. Your entries are safe on this phone.'); }
                    const { token } = await tokenResponse.json();
                    const response = await fetch('{{ route('beneficiaries.field.sync', [], false) }}', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ entries: this.queue.map(({ id, reference, type, delivered_on, description }) => ({ id, reference, type, delivered_on, description })) }) });
                    if (!response.ok) { throw new Error('Could not send yet. Your entries are safe on this phone.'); }
                    const { results } = await response.json();
                    const failed = {}; let sent = 0;
                    results.forEach(r => { if (r.ok) { sent++; } else { failed[r.id] = r.message; } });
                    this.queue = this.queue.filter(e => failed[e.id] !== undefined).map(e => ({ ...e, error: failed[e.id] }));
                    this.sentCount += sent; this.save();
                } catch (error) { this.message = error.message || 'Could not send yet.'; }
                this.syncing = false;
            }
        };
    }
</script>
@endsection
