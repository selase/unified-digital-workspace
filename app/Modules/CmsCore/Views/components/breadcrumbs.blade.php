@props(['items' => []])

@if(count($items) > 0)
    <nav aria-label="Breadcrumb" class="py-4">
        <ol class="flex items-center gap-1.5 text-sm text-gray-500">
            <li>
                <a href="{{ $cmsUrl->route('home') }}" class="transition hover:text-gray-700">Home</a>
            </li>
            @foreach($items as $item)
                <li class="flex items-center gap-1.5">
                    <svg class="h-4 w-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    @if(!empty($item['url']) && !$loop->last)
                        <a href="{{ $item['url'] }}" class="transition hover:text-gray-700">{{ $item['label'] }}</a>
                    @else
                        <span class="text-gray-900 font-medium">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
