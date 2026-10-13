<div class="kt-card p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Activity Logs</h2>
            <p class="text-xs text-muted-foreground">The most recent things this account did.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="kt-table table-auto kt-table-border" id="activity-table">
            <thead>
                <tr class="text-xs uppercase text-muted-foreground">
                    <th>When</th>
                    <th>What happened</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody class="text-sm text-foreground">
                @forelse ($activityLogs as $activityLog)
                    @php($entry = \App\Libraries\ActivityLogPresenter::describe($activityLog))
                    <tr>
                        <td class="whitespace-nowrap">{{ \App\Libraries\Helper::getHumanDate($activityLog->created_at) }}</td>
                        <td>{{ $entry['summary'] }}</td>
                        <td class="text-muted-foreground">{{ $entry['details'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="py-8 text-center text-muted-foreground">No activity logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
