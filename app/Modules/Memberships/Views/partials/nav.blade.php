@php
    $tabs = ['memberships.index' => ['Overview', 'memberships.index'], 'memberships.members' => ['Members', 'memberships.members.index'], 'memberships.types' => ['Membership types', 'memberships.types.index']];
    $current = request()->route()?->getName() ?? '';
@endphp
<div class="flex flex-col gap-4">
    <div class="flex flex-wrap items-center gap-1 rounded-lg bg-muted/30 p-1">
        @foreach($tabs as $key => $tab)
            @php $active = $key === 'memberships.index' ? $current === 'memberships.index' : str_starts_with($current, $key); @endphp
            <a href="{{ route($tab[1]) }}" class="kt-btn kt-btn-sm {{ $active ? 'kt-btn-primary' : 'kt-btn-ghost' }}">{{ $tab[0] }}</a>
        @endforeach
    </div>
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
</div>
