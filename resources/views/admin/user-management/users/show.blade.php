@extends('layouts.metronic.app')

@section('title', __('locale.labels.view_user_details'))

@section('content')
    @php
        $roleName = $user->roles()->first()?->name ?? 'No Role';
        $avatar = $user->photo ? Storage::url($user->photo) : $user->gravatar;
        $status = ucfirst((string) $user->status);
        $lastLogin = $user->lastLogin();
        $lastLoginIp = $user->last_login_ip ? '::'.$user->last_login_ip : 'N/A';
        $twoFactorEnabled = (bool) $user->two_factor_confirmed_at;
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">User Management</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ $user->displayName() }}</h1>
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        <span class="kt-badge kt-badge-primary">{{ $roleName }}</span>
                        <span class="text-xs text-muted-foreground">{{ $status }}</span>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('users.index') }}" class="kt-btn kt-btn-outline">Back to Users</a>
                    @can('update user')
                        <a href="{{ route('users.edit', $user) }}" class="kt-btn kt-btn-primary">Edit User</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-border bg-background p-6">
                <div class="flex flex-col items-center text-center">
                    <img src="{{ $avatar }}" alt="{{ $user->displayName() }}" class="size-24 rounded-full object-cover" />
                    <div class="mt-4 text-lg font-semibold text-foreground">{{ $user->displayName() }}</div>
                    <div class="text-xs text-muted-foreground">{{ $user->email }}</div>
                    <span class="kt-badge kt-badge-secondary mt-3">{{ $roleName }}</span>
                </div>

                <div class="mt-6 space-y-3 text-sm">
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Account ID</div>
                        <div class="text-sm text-foreground">{{ $user->uuid }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Phone</div>
                        <div class="text-sm text-foreground">{{ $user->phone_no }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Last Login</div>
                        <div class="text-sm text-foreground">{{ $lastLogin }}</div>
                    </div>
                    <div>
                        <div class="text-xs uppercase text-muted-foreground">Last Login IP</div>
                        <div class="text-sm text-foreground">{{ $lastLoginIp }}</div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-2 grid gap-6">
                <div class="rounded-xl border border-border bg-background p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Account Details</h2>
                            <p class="text-xs text-muted-foreground">Core profile information and status.</p>
                        </div>
                        <span class="kt-badge {{ $status === 'Active' ? 'kt-badge-success' : 'kt-badge-warning' }}">{{ $status }}</span>
                    </div>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 text-sm">
                        <div>
                            <div class="text-xs uppercase text-muted-foreground">Email</div>
                            <div class="text-sm text-foreground">{{ $user->email }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-muted-foreground">Role</div>
                            <div class="text-sm text-foreground">{{ $roleName }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-muted-foreground">Created At</div>
                            <div class="text-sm text-foreground">{{ $user->created_at?->format('M d, Y') }}</div>
                        </div>
                        <div>
                            <div class="text-xs uppercase text-muted-foreground">Last Seen</div>
                            <div class="text-sm text-foreground">{{ $lastLogin }}</div>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-background p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Security</h2>
                            <p class="text-xs text-muted-foreground">Two-factor authentication status.</p>
                        </div>
                        <span class="kt-badge {{ $twoFactorEnabled ? 'kt-badge-success' : 'kt-badge-secondary' }}">
                            {{ $twoFactorEnabled ? '2FA Enabled' : '2FA Not Enabled' }}
                        </span>
                    </div>
                    <div class="mt-4 text-sm text-muted-foreground">
                        {{ $twoFactorEnabled ? 'This user has completed two-factor setup.' : 'Enable 2FA to add an extra layer of security.' }}
                    </div>
                </div>

                <div class="rounded-xl border border-border bg-background p-6">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-semibold text-foreground">Recent Sessions</h2>
                            <p class="text-xs text-muted-foreground">Latest sign-in activity for this account.</p>
                        </div>
                        <button type="button" class="kt-btn kt-btn-outline">Sign out all</button>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="kt-table">
                            <thead>
                                <tr class="text-xs uppercase text-muted-foreground">
                                    <th>Location</th>
                                    <th>Device</th>
                                    <th>IP Address</th>
                                    <th class="text-right">Time</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-foreground">
                                <tr>
                                    <td>Unknown</td>
                                    <td>Web</td>
                                    <td>{{ $lastLoginIp }}</td>
                                    <td class="text-right">{{ $lastLogin }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-center text-xs text-muted-foreground">Additional session tracking will appear here.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
