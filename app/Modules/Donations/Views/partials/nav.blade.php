@php
    $tabs = [
        'donations.index' => ['Overview', 'donations.index'],
        'donations.donations' => ['Donations', 'donations.donations.index'],
        'donations.donors' => ['Donors', 'donations.donors.index'],
        'donations.pledges' => ['Pledges', 'donations.pledges.index'],
        'donations.campaigns' => ['Campaigns', 'donations.campaigns.index'],
    ];
    $current = request()->route()?->getName() ?? '';
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-1 rounded-lg bg-muted/30 p-1">
        @foreach($tabs as $key => $tab)
            @php $active = $key === 'donations.index' ? $current === 'donations.index' : str_starts_with($current, $key); @endphp
            <a href="{{ route($tab[1]) }}" class="kt-btn kt-btn-sm {{ $active ? 'kt-btn-primary' : 'kt-btn-ghost' }}">{{ $tab[0] }}</a>
        @endforeach
        @if(Route::has('finance.reports.funds'))
            <a href="{{ route('finance.reports.funds') }}" class="kt-btn kt-btn-sm kt-btn-ghost">Fund report</a>
        @endif
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
</div>
