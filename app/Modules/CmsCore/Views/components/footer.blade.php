@props(['menu' => null, 'footerText' => '', 'siteName' => 'Website'])

<footer class="border-t border-gray-100 bg-gray-50">
    <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        @if($menu && $menu->items->isNotEmpty())
            <nav class="mb-8 flex flex-wrap justify-center gap-x-8 gap-y-2">
                @foreach($menu->items->sortBy('sort_order') as $item)
                    <a
                        href="{{ $item->url }}"
                        class="text-sm text-gray-500 transition hover:text-gray-700"
                        @if($item->open_in_new_tab) target="_blank" rel="noopener" @endif
                    >
                        {{ $item->label }}
                    </a>
                @endforeach
            </nav>
        @endif

        <p class="text-center text-sm text-gray-400">
            {{ $footerText }}
        </p>
    </div>
</footer>
