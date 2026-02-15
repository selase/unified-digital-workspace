<div class="rounded-xl border border-border bg-background p-6">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-foreground">Login Sessions</h2>
            <p class="text-xs text-muted-foreground">Most recent sign-in activity for this user.</p>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="kt-table" id="login-session-table">
            <thead>
                <tr class="text-xs uppercase text-muted-foreground">
                    <th>Location</th>
                    <th>Device</th>
                    <th>IP Address</th>
                    <th>Login At</th>
                </tr>
            </thead>
            <tbody class="text-sm text-foreground">
                @forelse ($loginSessions as $loginSession)
                    <tr>
                        <td>{{ $loginSession->location ?: 'Unknown' }}</td>
                        <td>{{ $loginSession->getDevice() }}</td>
                        <td>{{ $loginSession->ip_address }}</td>
                        <td>{{ \App\Libraries\Helper::getReadableDate($loginSession->login_at) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-muted-foreground">No login sessions found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
