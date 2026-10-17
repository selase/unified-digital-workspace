@extends('layouts.metronic.app')

@section('title', 'Platform Credit Store')

@section('content')
<div class="flex flex-col gap-6">
    <x-metronic.page-header
        title="Platform Quota & Add-On Store"
        description="Top-up SMS notifications, bulk transactional emails, and AI token packs. Delivery-based quota billing."
    >
        <x-slot:actions>
            <a class="kt-btn kt-btn-outline" href="{{ route('store.index') }}">Back to Store Hub</a>
        </x-slot:actions>
    </x-metronic.page-header>

    @if(session('status') === 'success')
        <div class="p-4 rounded-xl bg-success/10 border border-success/20 text-success text-sm font-medium">
            {{ session('message') }}
        </div>
    @endif

    {{-- Credit Packages Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($packages as $pkg)
            <div class="kt-card p-6 flex flex-col justify-between border-t-4 {{ $pkg->credit_type === 'sms' ? 'border-t-primary' : ($pkg->credit_type === 'email' ? 'border-t-info' : 'border-t-secondary') }} hover:shadow-lg transition">
                <div>
                    <div class="flex items-center justify-between mb-3">
                        <span class="kt-badge kt-badge-sm kt-badge-outline uppercase text-[10px]">
                            {{ strtoupper($pkg->credit_type) }}
                        </span>
                        <span class="text-xs text-muted-foreground font-semibold">Prepaid</span>
                    </div>

                    <h3 class="text-lg font-bold text-foreground">{{ $pkg->name }}</h3>
                    <div class="text-3xl font-extrabold text-primary my-3">
                        {{ number_format($pkg->amount_credits) }} <span class="text-sm font-normal text-muted-foreground">Credits</span>
                    </div>
                    <p class="text-xs text-muted-foreground mb-6">
                        Instantly credited to tenant wallet upon confirmation. Delivery-guaranteed.
                    </p>
                </div>

                <div class="pt-4 border-t border-border flex items-center justify-between">
                    <div>
                        <div class="text-[10px] uppercase font-bold text-muted-foreground">Price</div>
                        <div class="text-lg font-bold text-foreground">
                            {{ $pkg->currency }} {{ number_format($pkg->price_cents / 100, 2) }}
                        </div>
                    </div>

                    <form action="{{ route('store.platform.purchase') }}" method="POST">
                        @csrf
                        <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                        <button type="submit" class="kt-btn kt-btn-sm kt-btn-primary">
                            ⚡ Purchase Now
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="md:col-span-3 kt-card p-8 text-center text-muted-foreground">
                No platform credit packages configured at this time.
            </div>
        @endforelse
    </div>
</div>
@endsection

