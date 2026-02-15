@extends('layouts.metronic.app')

@section('title', 'View Lead - ' . $lead->name)

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Growth</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">Lead Details</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Review and manage enterprise lead submissions.</p>
                </div>
                <a href="{{ route('admin.leads.index') }}" class="kt-btn kt-btn-outline">
                    Back to List
                </a>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <dl class="grid gap-5 md:grid-cols-2">
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">Full Name</dt>
                    <dd class="mt-1 text-sm font-medium text-foreground">{{ $lead->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">Email Address</dt>
                    <dd class="mt-1 text-sm font-medium text-foreground">{{ $lead->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">Company</dt>
                    <dd class="mt-1 text-sm font-medium text-foreground">{{ $lead->company ?? 'N/A' }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">IP Address</dt>
                    <dd class="mt-1 text-sm font-medium text-foreground">{{ $lead->ip_address }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">Status</dt>
                    <dd class="mt-1">
                        <span class="kt-badge kt-badge-outline kt-badge-primary">{{ strtoupper($lead->status) }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">Created At</dt>
                    <dd class="mt-1 text-sm font-medium text-foreground">
                        {{ $lead->created_at->format('M d, Y H:i:s') }} ({{ $lead->created_at->diffForHumans() }})
                    </dd>
                </div>
                <div class="md:col-span-2">
                    <dt class="text-xs uppercase text-muted-foreground">Message</dt>
                    <dd class="mt-2 rounded-lg border border-border bg-muted/40 p-4 text-sm text-foreground">{{ $lead->message }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex justify-end">
                <form action="{{ route('admin.leads.destroy', $lead->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="kt-btn kt-btn-sm kt-btn-danger" onclick="return confirm('Are you sure you want to delete this lead?')">
                        Delete Lead
                    </button>
                </form>
            </div>
        </div>
    </section>
@endsection
