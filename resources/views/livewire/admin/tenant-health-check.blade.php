<div>
    <div class="mb-6 rounded-xl border border-border bg-background p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-foreground">Infrastructure Health</h3>
                <p class="mt-1 text-xs text-muted-foreground">Real-time status of tenant resources.</p>
            </div>
            <div>
                <button wire:click="runCheck" class="kt-btn kt-btn-sm kt-btn-primary" wire:loading.attr="disabled">
                    <span wire:loading.remove>Run Full Health Check</span>
                    <span wire:loading>Checking...</span>
                </button>
            </div>
        </div>

        <div class="mt-6">
            @if($results)
                @if(isset($results['error']))
                    <div class="mb-6 flex items-start gap-3 rounded-lg border border-destructive/40 bg-destructive/10 p-4">
                        <span class="mt-0.5 text-destructive">
                            <i class="fas fa-exclamation-triangle text-xl"></i>
                        </span>
                        <div class="space-y-1">
                            <h4 class="text-sm font-semibold text-destructive">System Error</h4>
                            <p class="text-sm text-destructive">{{ $results['error'] }}</p>
                        </div>
                    </div>
                @else
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="rounded-lg border border-dashed p-4 {{ $results['database']['status'] === 'ok' ? 'border-success/40 bg-success/5' : 'border-destructive/40 bg-destructive/5' }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-foreground">Database</p>
                                @if($results['database']['status'] === 'ok')
                                    <span class="kt-badge kt-badge-success">Active</span>
                                @else
                                    <span class="kt-badge kt-badge-destructive">Error</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $results['database']['message'] }}
                                @if(isset($results['database']['database_name']))
                                    <br><span class="text-muted-foreground">DB: {{ $results['database']['database_name'] }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="rounded-lg border border-dashed p-4 {{ $results['storage']['status'] === 'ok' ? 'border-success/40 bg-success/5' : 'border-destructive/40 bg-destructive/5' }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-foreground">Storage</p>
                                @if($results['storage']['status'] === 'ok')
                                    <span class="kt-badge kt-badge-success">Active</span>
                                @else
                                    <span class="kt-badge kt-badge-destructive">Error</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $results['storage']['message'] }}
                                @if(isset($results['storage']['disk']))
                                    <br><span class="text-muted-foreground">Driver: {{ $results['storage']['disk'] }}</span>
                                @endif
                            </p>
                        </div>

                        <div class="rounded-lg border border-dashed p-4 {{ $results['features']['status'] === 'ok' ? 'border-success/40 bg-success/5' : 'border-warning/50 bg-warning/10' }}">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-foreground">Features</p>
                                @if($results['features']['status'] === 'ok')
                                    <span class="kt-badge kt-badge-success">Synced</span>
                                @else
                                    <span class="kt-badge kt-badge-warning">Warning</span>
                                @endif
                            </div>
                            <p class="mt-2 text-xs text-muted-foreground">
                                {{ $results['features']['message'] }}
                            </p>
                        </div>
                    </div>

                    <p class="mt-4 text-xs text-muted-foreground">
                        Last checked: {{ \Carbon\Carbon::parse($results['last_checked_at'])->diffForHumans() }}
                    </p>
                @endif
            @else
                <div class="rounded-lg border border-dashed border-border p-5 text-center">
                    <span class="text-sm text-muted-foreground">No health check data available. Click "Run Full Health Check" to begin.</span>
                </div>
            @endif
        </div>
    </div>
</div>
