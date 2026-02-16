@extends('layouts.metronic.app')

@section('title', __('API Keys'))

@section('content')
    @php
        $subdomain = request()->route('subdomain');
        $storeUrl = $subdomain ? route('tenant.api-keys.store', ['subdomain' => $subdomain]) : '#';
        $indexUrl = $subdomain ? route('tenant.api-keys.index', ['subdomain' => $subdomain]) : '#';
        $activeKeyCount = $apiKeys->filter(fn ($key) => !$key->isRevoked() && !$key->isExpired())->count();
        $expiredKeyCount = $apiKeys->filter(fn ($key) => $key->isExpired())->count();
        $revokedKeyCount = $apiKeys->filter(fn ($key) => $key->isRevoked())->count();
    @endphp

    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">Developer</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('API Keys') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Generate and revoke tenant API keys used for integrations and automation.</p>
                </div>
                <button type="button" class="kt-btn kt-btn-primary" data-kt-modal-toggle="#generate_key_modal">
                    <i class="ki-filled ki-plus text-base"></i>
                    {{ __('Generate New Key') }}
                </button>
            </div>

            <div class="mt-5 grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ __('Active') }}</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $activeKeyCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ __('Expired') }}</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $expiredKeyCount }}</p>
                </div>
                <div class="rounded-lg border border-border bg-muted/30 p-4">
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">{{ __('Revoked') }}</p>
                    <p class="mt-2 text-xl font-semibold text-foreground">{{ $revokedKeyCount }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-foreground">{{ __('Issued Keys') }}</h2>
                <div class="w-full sm:w-72">
                    <input id="apiKeySearch" type="text" class="kt-input w-full" placeholder="{{ __('Search key name, creator, or status') }}" />
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="kt-table table-auto kt-table-border" id="api-keys-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>{{ __('Name') }}</th>
                            <th>{{ __('Key Hint') }}</th>
                            <th>{{ __('Created By') }}</th>
                            <th>{{ __('Last Used') }}</th>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($apiKeys as $key)
                            <tr data-key-search="{{ strtolower($key->name . ' ' . ($key->user?->displayName() ?? 'system') . ' ' . ($key->isRevoked() ? 'revoked' : ($key->isExpired() ? 'expired' : 'active'))) }}">
                                <td class="font-medium text-foreground">{{ $key->name }}</td>
                                <td><code>sk_...{{ $key->key_hint }}</code></td>
                                <td>{{ $key->user?->displayName() ?? 'System' }}</td>
                                <td>{{ $key->last_used_at?->diffForHumans() ?? __('Never') }}</td>
                                <td>{{ $key->created_at->format('Y-m-d H:i') }}</td>
                                <td>
                                    @if($key->isRevoked())
                                        <span class="kt-badge kt-badge-outline kt-badge-danger">{{ __('Revoked') }}</span>
                                    @elseif($key->isExpired())
                                        <span class="kt-badge kt-badge-outline kt-badge-warning">{{ __('Expired') }}</span>
                                    @else
                                        <span class="kt-badge kt-badge-outline kt-badge-success">{{ __('Active') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    @if(!$key->isRevoked())
                                        <button
                                            type="button"
                                            class="kt-btn kt-btn-sm kt-btn-outline kt-btn-danger"
                                            onclick="revokeApiKey('{{ $key->id }}', @js($key->name))"
                                        >
                                            {{ __('Revoke') }}
                                        </button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-muted-foreground">
                                    {{ __('No API keys found. Generate one to get started.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <button type="button" id="openRevealKeyModal" class="hidden" data-kt-modal-toggle="#reveal_key_modal"></button>

    <div class="kt-modal" data-kt-modal="true" id="generate_key_modal">
        <div class="kt-modal-content max-w-xl top-9">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">{{ __('Generate API Key') }}</h3>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true" id="generateKeyModalClose">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>
            <form id="generateKeyForm" class="grid gap-4 p-6">
                <div class="grid gap-1">
                    <label for="key_name" class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ __('Key Name') }}</label>
                    <input type="text" class="kt-input" placeholder="{{ __('Enter key name') }}" name="name" id="key_name" required />
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">{{ __('Discard') }}</button>
                    <button type="submit" class="kt-btn kt-btn-primary" id="generateKeySubmit">{{ __('Generate') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="kt-modal" data-kt-modal="true" id="reveal_key_modal">
        <div class="kt-modal-content max-w-xl top-9">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">{{ __('New API Key Generated') }}</h3>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>
            <div class="grid gap-4 p-6">
                <div class="rounded-lg border border-warning/40 bg-warning/10 p-4 text-sm text-warning">
                    {{ __('For security reasons, this key is shown only once. Save it now.') }}
                </div>
                <div class="grid gap-1">
                    <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground" for="plain_api_key">{{ __('Your API Key') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="text" id="plain_api_key" class="kt-input" readonly value="" />
                        <button class="kt-btn kt-btn-outline" type="button" onclick="copyToClipboard('plain_api_key')">{{ __('Copy') }}</button>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="button" class="kt-btn kt-btn-primary" onclick="window.location.reload()">{{ __('I have saved the key') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        const apiKeyStoreUrl = @json($storeUrl);
        const apiKeyIndexUrl = @json($indexUrl);
        const csrfToken = @json(csrf_token());

        document.getElementById('generateKeyForm')?.addEventListener('submit', async function (event) {
            event.preventDefault();

            const submitButton = document.getElementById('generateKeySubmit');
            const keyNameInput = document.getElementById('key_name');

            submitButton?.setAttribute('disabled', 'disabled');
            submitButton?.setAttribute('data-kt-indicator', 'on');

            try {
                const response = await fetch(apiKeyStoreUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        name: keyNameInput?.value,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to generate API key');
                }

                const payload = await response.json();
                document.getElementById('plain_api_key').value = payload.key;
                document.getElementById('generateKeyModalClose')?.click();
                document.getElementById('openRevealKeyModal')?.click();
            } catch (error) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Error generating key');
                }
            } finally {
                submitButton?.removeAttribute('disabled');
                submitButton?.removeAttribute('data-kt-indicator');
            }
        });

        async function revokeApiKey(id, name) {
            const confirmText = `Are you sure you want to revoke the API key '${name}'? This action cannot be undone.`;

            if (typeof Swal !== 'undefined') {
                const result = await Swal.fire({
                    text: confirmText,
                    icon: 'warning',
                    showCancelButton: true,
                    buttonsStyling: false,
                    confirmButtonText: 'Yes, revoke it!',
                    cancelButtonText: 'No, cancel',
                    customClass: {
                        confirmButton: 'kt-btn kt-btn-danger',
                        cancelButton: 'kt-btn kt-btn-outline',
                    },
                });

                if (!result.isConfirmed) {
                    return;
                }
            } else if (!window.confirm(confirmText)) {
                return;
            }

            try {
                const response = await fetch(`${apiKeyIndexUrl}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) {
                    throw new Error('Failed to revoke API key');
                }

                window.location.reload();
            } catch (error) {
                if (typeof toastr !== 'undefined') {
                    toastr.error('Error revoking key');
                }
            }
        }

        function copyToClipboard(id) {
            const source = document.getElementById(id);
            if (!source) {
                return;
            }

            source.select();
            source.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(source.value).then(() => {
                if (typeof toastr !== 'undefined') {
                    toastr.success('Key copied to clipboard');
                }
            });
        }

        document.getElementById('apiKeySearch')?.addEventListener('keyup', function (event) {
            const query = event.target.value.toLowerCase().trim();
            document.querySelectorAll('#api-keys-table tbody tr[data-key-search]').forEach((row) => {
                const haystack = row.getAttribute('data-key-search') || '';
                row.style.display = haystack.includes(query) ? '' : 'none';
            });
        });
    </script>
@endpush
