<div class="flex flex-col gap-4">
    @if(session('success'))<div class="rounded-lg bg-green-50 p-3 text-sm text-green-700">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-disc pl-5">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
</div>
