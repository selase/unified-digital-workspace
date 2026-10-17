@extends('layouts.metronic.app')

@section('title', "Account {$account->code} - {$account->name}")

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground">{{ $account->code }}</span>
                <h1 class="text-xl font-bold text-foreground">{{ $account->name }}</h1>
                <span class="kt-badge kt-badge-sm kt-badge-outline uppercase">{{ $account->account_type }}</span>
                @if($account->status === 'active')
                    <span class="kt-badge kt-badge-sm kt-badge-success">Active</span>
                @else
                    <span class="kt-badge kt-badge-sm kt-badge-warning uppercase">{{ $account->status }}</span>
                @endif
            </div>
            <p class="text-xs text-muted-foreground mt-1">{{ $account->industry ?? 'General Industry' }} &bull; Registered {{ $account->created_at?->format('d/m/Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.accounts.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Back to Accounts</a>
            @can('crm.accounts.manage')
                <a href="{{ route('crm.accounts.edit', $account) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit Account</a>
                <form action="{{ route('crm.accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('Soft-delete this client account and all associations?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-destructive">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Profile Card --}}
        <div class="flex flex-col gap-6">
            <div class="kt-card p-5 space-y-3">
                <h3 class="font-semibold text-sm border-b border-border pb-2">Account Overview</h3>
                <div class="text-xs space-y-2">
                    <div>
                        <span class="text-muted-foreground">Email:</span>
                        <span class="font-medium text-foreground ml-1">{{ $account->email ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Phone:</span>
                        <span class="font-medium text-foreground ml-1">{{ $account->phone ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Office Address:</span>
                        <span class="font-medium text-foreground ml-1">{{ $account->address ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Account Owner:</span>
                        <span class="font-medium text-foreground ml-1">{{ $account->owner?->first_name ? $account->owner->first_name.' '.$account->owner->last_name : 'Unassigned' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Client Portal:</span>
                        <span class="font-medium ml-1">
                            @if($account->portal_user_id)
                                <span class="kt-badge kt-badge-2xs kt-badge-success">Enabled</span>
                            @else
                                <span class="kt-badge kt-badge-2xs kt-badge-outline">Internal Record Only</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            {{-- Quick Add Contact Form --}}
            @can('crm.accounts.manage')
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-3">Add Contact Person</h3>
                <form action="{{ route('crm.accounts.contacts.store', $account) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">First Name *</label>
                            <input type="text" name="first_name" class="kt-input text-xs" required>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Last Name</label>
                            <input type="text" name="last_name" class="kt-input text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Email</label>
                        <input type="email" name="email" class="kt-input text-xs">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="kt-form-label text-xs">Phone</label>
                            <input type="text" name="phone" class="kt-input text-xs">
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Job Title</label>
                            <input type="text" name="job_title" class="kt-input text-xs" placeholder="e.g. CFO">
                        </div>
                    </div>
                    <label class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                        <input type="checkbox" name="is_primary" value="1" class="kt-checkbox kt-checkbox-sm">
                        Designate Primary Contact
                    </label>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full justify-center">Add Contact</button>
                </form>
            </div>
            @endcan
        </div>

        {{-- Center & Right: Contacts, Deals, Activity Trail --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            {{-- Contacts Directory --}}
            <div class="kt-card">
                <div class="kt-card-header">
                    <h3 class="kt-card-title text-sm">Key Contact Persons</h3>
                </div>
                <div class="kt-card-table kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Primary</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($account->contacts as $c)
                                <tr>
                                    <td class="font-semibold text-foreground">{{ $c->fullName() }}</td>
                                    <td class="text-xs text-muted-foreground">{{ $c->job_title ?? '—' }}</td>
                                    <td class="text-xs">{{ $c->email ?? '—' }}</td>
                                    <td class="text-xs">{{ $c->phone ?? '—' }}</td>
                                    <td>
                                        @if($c->is_primary)
                                            <span class="kt-badge kt-badge-xs kt-badge-primary">Primary</span>
                                        @else
                                            <span class="text-2xs text-muted-foreground">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        @can('crm.accounts.manage')
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('crm.contacts.edit', $c) }}" class="kt-btn kt-btn-2xs kt-btn-outline">Edit</a>
                                                <form action="{{ route('crm.contacts.destroy', $c) }}" method="POST" class="inline" onsubmit="return confirm('Remove contact?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="kt-btn kt-btn-2xs kt-btn-destructive">Remove</button>
                                                </form>
                                            </div>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-xs text-muted-foreground">No contacts added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Commercial Deals for this Account --}}
            <div class="kt-card">
                <div class="kt-card-header flex items-center justify-between">
                    <h3 class="kt-card-title text-sm">Associated Commercial Deals</h3>
                    <a href="{{ route('crm.pipeline') }}" class="kt-btn kt-btn-2xs kt-btn-outline">Open Kanban</a>
                </div>
                <div class="kt-card-table kt-scrollable-x-auto">
                    <table class="kt-table table-auto kt-table-border">
                        <thead>
                            <tr>
                                <th>Deal Title</th>
                                <th>Stage</th>
                                <th>Value</th>
                                <th>Win %</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($account->deals as $deal)
                                <tr>
                                    <td class="font-semibold text-foreground">
                                        <a href="{{ route('crm.deals.show', $deal) }}" class="text-primary hover:underline">
                                            {{ $deal->title }}
                                        </a>
                                    </td>
                                    <td><span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $deal->stage) }}</span></td>
                                    <td class="font-mono text-xs font-semibold">{{ $deal->formattedValue() }}</td>
                                    <td><span class="kt-badge kt-badge-xs kt-badge-outline">{{ $deal->probability }}%</span></td>
                                    <td class="text-end">
                                        <a href="{{ route('crm.deals.show', $deal) }}" class="kt-btn kt-btn-xs kt-btn-outline">View</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-xs text-muted-foreground">No deals created for this client account yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Activity Trail on Account --}}
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-4">Account Activity History</h3>

                @can('crm.accounts.manage')
                    <form action="{{ route('crm.accounts.activities.store', $account) }}" method="POST" class="mb-4 pb-4 border-b border-border space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>
                                <select name="activity_type" class="kt-select text-xs" required>
                                    <option value="note">Internal Note</option>
                                    <option value="call">Call Logged</option>
                                    <option value="meeting">Meeting Held</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <input type="text" name="summary" class="kt-input text-xs" placeholder="Summary of interaction..." required>
                            </div>
                        </div>
                        <button type="submit" class="kt-btn kt-btn-xs kt-btn-outline">Log Activity</button>
                    </form>
                @endcan

                <div class="space-y-3">
                    @forelse($account->activities as $act)
                        <div class="flex items-start gap-3 text-xs border-b border-border/40 pb-2 last:border-none">
                            <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $act->activity_type) }}</span>
                            <div class="flex-1">
                                <div class="font-semibold text-foreground">{{ $act->summary }}</div>
                                @if($act->details)
                                    <div class="text-muted-foreground mt-1">{{ $act->details }}</div>
                                @endif
                                <div class="text-2xs text-muted-foreground mt-1">
                                    {{ $act->occurred_at->format('d M Y, H:i') }} &bull; By {{ $act->loggedBy?->first_name ?? 'System' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-xs text-muted-foreground">No activities recorded on this account yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

