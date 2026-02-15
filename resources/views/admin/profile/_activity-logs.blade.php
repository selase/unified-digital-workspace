<div class="rounded-xl border border-border bg-background p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Activity Logs</h2>
            <p class="text-xs text-muted-foreground">Recorded user actions and associated metadata.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="kt-table" id="activity-table">
            <thead>
                <tr class="text-xs uppercase text-muted-foreground">
                    <th>{{ __('locale.labels.log_id') }}</th>
                    <th>{{ __('locale.labels.location') }}</th>
                    <th>{{ __('locale.labels.event') }}</th>
                    <th>{{ __('locale.labels.subject_type') }}</th>
                    <th>{{ __('locale.labels.created_at') }}</th>
                    <th>{{ __('locale.labels.properties') }}</th>
                </tr>
            </thead>
            <tbody class="text-sm text-foreground">
                @forelse ($activityLogs as $activityLog)
                    <tr>
                        <td>{{ $activityLog->id }}</td>
                        <td>{{ $activityLog->log_name }}</td>
                        <td>{{ $activityLog->description }}</td>
                        <td>{{ $activityLog->subject_type }}</td>
                        <td>{{ \App\Libraries\Helper::getHumanDate($activityLog->created_at) }}</td>
                        <td>
                            <textarea rows="5" class="kt-textarea min-w-72" disabled>{{ $activityLog->properties }}</textarea>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-muted-foreground">No activity logs found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
