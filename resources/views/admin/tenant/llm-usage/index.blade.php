@extends('layouts.metronic.app')

@section('title', __('LLM Usage'))

@section('content')
    <section class="grid gap-6">
        <div class="rounded-xl border border-border bg-background p-6 lg:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs uppercase tracking-wide text-muted-foreground">AI Billing</p>
                    <h1 class="mt-2 text-2xl font-semibold text-foreground">{{ __('LLM Usage') }}</h1>
                    <p class="mt-2 text-sm text-muted-foreground">Track token usage and purchase top-up packs when required.</p>
                </div>
                <button type="button" class="kt-btn kt-btn-primary" data-kt-modal-toggle="#kt_modal_buy_tokens">
                    <i class="ki-filled ki-plus text-base"></i>
                    {{ __('Purchase Pack') }}
                </button>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-2">
            <div class="rounded-xl border border-primary/30 bg-primary/[0.08] p-6">
                <p class="text-xs uppercase text-primary">{{ __('Top-up Token Balance') }}</p>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ number_format((float) $topupBalance) }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('Used when your monthly quota is exhausted.') }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-6">
                <p class="text-xs uppercase text-muted-foreground">{{ __('Add More Tokens') }}</p>
                <p class="mt-2 text-sm text-muted-foreground">{{ __('Purchase one-time token packs to avoid service interruption.') }}</p>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-4">
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase text-muted-foreground">{{ __('Total Tokens') }}</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((float) ($totalUsage->total_tokens ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase text-muted-foreground">{{ __('Total Cost (Est.)') }}</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">${{ number_format((float) ($totalUsage->total_cost ?? 0), 4) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase text-muted-foreground">{{ __('Input Tokens') }}</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((float) ($totalUsage->prompt_tokens ?? 0)) }}</p>
            </div>
            <div class="rounded-xl border border-border bg-background p-5">
                <p class="text-xs uppercase text-muted-foreground">{{ __('Output Tokens') }}</p>
                <p class="mt-1 text-2xl font-semibold text-foreground">{{ number_format((float) ($totalUsage->completion_tokens ?? 0)) }}</p>
            </div>
        </div>

        <div class="rounded-xl border border-border bg-background p-6">
            <div class="mb-4">
                <h2 class="text-lg font-semibold text-foreground">{{ __('Recent Activity') }}</h2>
                <p class="text-xs text-muted-foreground">{{ __('Latest LLM requests, token usage, and estimated cost.') }}</p>
            </div>

            <div class="overflow-x-auto">
                <table class="kt-table">
                    <thead>
                        <tr class="text-xs uppercase text-muted-foreground">
                            <th>{{ __('Date') }}</th>
                            <th>{{ __('Model') }}</th>
                            <th>{{ __('User / Key') }}</th>
                            <th>{{ __('Tokens') }}</th>
                            <th>{{ __('Cost') }}</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-foreground">
                        @forelse($recentUsage as $usage)
                            <tr>
                                <td>{{ $usage->created_at->format('M d, H:i:s') }}</td>
                                <td><span class="kt-badge kt-badge-outline kt-badge-primary">{{ $usage->model }}</span></td>
                                <td>
                                    @if($usage->user)
                                        <span class="font-medium">{{ $usage->user->name }}</span>
                                    @elseif($usage->apiKey)
                                        <span class="kt-badge kt-badge-outline kt-badge-info">Key: ...{{ $usage->apiKey->key_hint }}</span>
                                    @else
                                        <span class="text-muted-foreground">System</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="flex flex-col">
                                        <span>{{ number_format((float) $usage->total_tokens) }}</span>
                                        <span class="text-xs text-muted-foreground">{{ number_format((float) $usage->prompt_tokens) }} in / {{ number_format((float) $usage->completion_tokens) }} out</span>
                                    </div>
                                </td>
                                <td>
                                    @if($usage->cost_usd > 0)
                                        ${{ number_format((float) $usage->cost_usd, 5) }}
                                    @else
                                        -
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-muted-foreground">{{ __('No usage recorded yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                {{ $recentUsage->links() }}
            </div>
        </div>
    </section>
@endsection

@push('modals')
    <div class="kt-modal" data-kt-modal="true" id="kt_modal_buy_tokens">
        <div class="kt-modal-content max-w-2xl top-9">
            <div class="kt-modal-header">
                <h3 class="kt-modal-title">{{ __('Purchase Token Pack') }}</h3>
                <button type="button" class="kt-btn kt-btn-sm kt-btn-icon kt-btn-ghost" data-kt-modal-dismiss="true">
                    <i class="ki-filled ki-cross"></i>
                </button>
            </div>
            <form id="kt_modal_buy_tokens_form" class="grid gap-4 p-6" action="{{ route('billing.llm-checkout') }}" method="POST">
                @csrf

                <div class="grid gap-2">
                    <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ __('Select a Token Pack') }}</label>
                    <div class="grid gap-3">
                        @foreach($tokenPacks as $key => $pack)
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-border bg-muted/20 p-4">
                                <input class="kt-radio mt-1" type="radio" name="pack" value="{{ $key }}" {{ $loop->first ? 'checked' : '' }} />
                                <span>
                                    <span class="block text-sm font-semibold text-foreground">{{ $pack['name'] }}</span>
                                    <span class="block text-xs text-muted-foreground">{{ number_format((float) $pack['tokens']) }} tokens</span>
                                    <span class="mt-1 block text-sm font-semibold text-primary">${{ number_format((float) $pack['price'], 2) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" class="kt-btn kt-btn-outline" data-kt-modal-dismiss="true">{{ __('Cancel') }}</button>
                    <button type="submit" class="kt-btn kt-btn-primary">{{ __('Checkout & Pay') }}</button>
                </div>
            </form>
        </div>
    </div>
@endpush
