@extends('layouts.metronic.app')

@section('title', "Lead {$lead->reference_code} - {$lead->full_name}")

@section('content')
<div class="flex flex-col gap-6">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2">
                <span class="font-mono text-xs font-semibold px-2 py-0.5 rounded bg-muted text-muted-foreground">{{ $lead->reference_code }}</span>
                <h1 class="text-xl font-bold text-foreground">{{ $lead->full_name }}</h1>
                @if($lead->status === 'converted')
                    <span class="kt-badge kt-badge-sm kt-badge-success">Converted</span>
                @elseif($lead->status === 'lost')
                    <span class="kt-badge kt-badge-sm kt-badge-destructive">Lost</span>
                @elseif($lead->status === 'qualified')
                    <span class="kt-badge kt-badge-sm kt-badge-primary">Qualified</span>
                @else
                    <span class="kt-badge kt-badge-sm kt-badge-warning uppercase">{{ $lead->status }}</span>
                @endif
            </div>
            <p class="text-xs text-muted-foreground mt-1">Source: <span class="uppercase font-semibold">{{ str_replace('_', ' ', $lead->source) }}</span> &bull; Captured {{ $lead->created_at?->format('d/m/Y H:i') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('crm.leads.index') }}" class="kt-btn kt-btn-sm kt-btn-outline">Back to Leads</a>
            @can('crm.leads.manage')
                <a href="{{ route('crm.leads.edit', $lead) }}" class="kt-btn kt-btn-sm kt-btn-outline">Edit Lead</a>
                <form action="{{ route('crm.leads.destroy', $lead) }}" method="POST" onsubmit="return confirm('Soft-delete this lead record?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-destructive">Delete</button>
                </form>
            @endcan
        </div>
    </div>

    {{-- Main Content Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Left: Details & Actions --}}
        <div class="flex flex-col gap-6">
            {{-- Contact Information --}}
            <div class="kt-card p-5 space-y-3">
                <h3 class="font-semibold text-sm border-b border-border pb-2">Prospect Profile</h3>
                <div class="text-xs space-y-2">
                    <div>
                        <span class="text-muted-foreground">Email:</span>
                        <span class="font-medium text-foreground ml-1">{{ $lead->email ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Phone:</span>
                        <span class="font-medium text-foreground ml-1">{{ $lead->phone ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Organisation:</span>
                        <span class="font-medium text-foreground ml-1">{{ $lead->organisation ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Enquiry Type:</span>
                        <span class="font-medium text-foreground ml-1">{{ $lead->enquiry_type ?? 'general' }}</span>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Assigned Consultant:</span>
                        <span class="font-medium text-foreground ml-1">{{ $lead->assignedTo?->first_name ? $lead->assignedTo->first_name.' '.$lead->assignedTo->last_name : 'Unassigned' }}</span>
                    </div>
                </div>

                @if($lead->message)
                    <div class="pt-3 border-t border-border">
                        <span class="text-2xs text-muted-foreground uppercase font-semibold">Inquiry Notes</span>
                        <p class="text-xs text-foreground mt-1 whitespace-pre-line bg-muted/40 p-3 rounded">{{ $lead->message }}</p>
                    </div>
                @endif
            </div>

            {{-- Status Transition Panel --}}
            @can('crm.leads.manage')
            <div class="kt-card p-5 space-y-3">
                <h3 class="font-semibold text-sm border-b border-border pb-2">Status Progression</h3>
                <form action="{{ route('crm.leads.status', $lead) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="kt-form-label text-xs">Transition Status</label>
                        <select name="status" class="kt-select text-xs">
                            @foreach(['new' => 'New', 'contacted' => 'Contacted', 'qualified' => 'Qualified', 'lost' => 'Lost'] as $st => $label)
                                <option value="{{ $st }}" @selected($lead->status === $st)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary w-full justify-center">Update Status</button>
                </form>

                @if($lead->status !== 'converted')
                    @can('crm.convert')
                    <div class="pt-3 border-t border-border">
                        <span class="text-xs text-muted-foreground block mb-2">Ready to convert prospect into full client account?</span>
                        <form action="{{ route('crm.leads.convert', $lead) }}" method="POST" class="space-y-2">
                            @csrf
                            <label class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                <input type="checkbox" name="provision_portal_user" value="1" checked class="kt-checkbox kt-checkbox-sm">
                                Provision Client Portal Access
                            </label>
                            <button type="submit" class="kt-btn kt-btn-sm kt-btn-success w-full justify-center">Convert to Client Account</button>
                        </form>
                    </div>
                    @endcan
                @else
                    <div class="pt-3 border-t border-border">
                        <span class="text-xs text-success font-semibold">Converted to Account:</span>
                        <div class="text-xs text-foreground mt-1 font-mono">
                            <a href="{{ route('crm.accounts.show', $lead->convertedAccount) }}" class="text-primary hover:underline">
                                {{ $lead->convertedAccount?->name }} ({{ $lead->convertedAccount?->code }})
                            </a>
                        </div>
                    </div>
                @endif
            </div>
            @endcan
        </div>

        {{-- Right: Activity History Timeline & Logging --}}
        <div class="lg:col-span-2 flex flex-col gap-6">
            {{-- Log Activity Form --}}
            @can('crm.leads.manage')
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-3">Log Interaction / Activity</h3>
                <form action="{{ route('crm.leads.activities.store', $lead) }}" method="POST" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="kt-form-label text-xs">Activity Type *</label>
                            <select name="activity_type" class="kt-select text-xs" required>
                                <option value="call">Phone Call</option>
                                <option value="meeting">Physical / Zoom Meeting</option>
                                <option value="note">Internal Memo / Note</option>
                                <option value="email">Email Sent / Received</option>
                                <option value="walk_in_visit">Walk-In Reception</option>
                            </select>
                        </div>
                        <div>
                            <label class="kt-form-label text-xs">Occurred At</label>
                            <input type="datetime-local" name="occurred_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="kt-input text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Summary *</label>
                        <input type="text" name="summary" class="kt-input text-xs" placeholder="e.g. Discussed survey plan and agreed on site visit..." required>
                    </div>
                    <div>
                        <label class="kt-form-label text-xs">Details (Optional)</label>
                        <textarea name="details" rows="2" class="kt-textarea text-xs" placeholder="Full meeting notes or verbatim minutes..."></textarea>
                    </div>
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-outline">Add to Activity Trail</button>
                </form>
            </div>
            @endcan

            {{-- Activity Trail Timeline --}}
            <div class="kt-card p-5">
                <h3 class="font-semibold text-sm border-b border-border pb-2 mb-4">Activity & Audit Trail</h3>
                <div class="space-y-4">
                    @forelse($lead->activities as $act)
                        <div class="flex items-start gap-3 text-xs border-b border-border/40 pb-3 last:border-none">
                            <span class="kt-badge kt-badge-xs kt-badge-outline uppercase">{{ str_replace('_', ' ', $act->activity_type) }}</span>
                            <div class="flex-1">
                                <div class="font-semibold text-foreground">{{ $act->summary }}</div>
                                @if($act->details)
                                    <div class="text-muted-foreground mt-1">{{ $act->details }}</div>
                                @endif
                                <div class="text-2xs text-muted-foreground mt-1">
                                    {{ $act->occurred_at->format('d M Y, H:i') }} &bull; Logged by {{ $act->loggedBy?->first_name ?? 'System' }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-muted-foreground">No activities recorded on this lead yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

