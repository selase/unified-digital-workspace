@props(['menu' => null, 'logo' => null, 'siteName' => 'Website'])

@php
    /**
     * Resolve a menu item's URL — use explicit url first, then derive from linked post.
     */
    $resolveUrl = function (\App\Modules\CmsCore\Models\MenuItem $item): string {
        if ($item->url) {
            return $item->url;
        }

        if ($item->post_id && $item->relationLoaded('post') && $item->post) {
            $postType = $item->post->postType?->slug ?? 'page';

            return $postType === 'page'
                ? $cmsUrl->route('pages.show', $item->post->slug)
                : $cmsUrl->route('posts.show', $item->post->slug);
        }

        return '#';
    };
@endphp

<header class="border-b border-gray-100 bg-white">
    <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ $cmsUrl->route('home') }}" class="flex items-center gap-3">
            @if($logo)
                <img
                    src="{{ Storage::disk($logo->disk)->url($logo->path) }}"
                    alt="{{ $siteName }}"
                    class="h-8 w-auto"
                />
            @endif
            <span class="text-xl font-semibold text-gray-900">{{ $siteName }}</span>
        </a>

        @if($menu && $menu->items->isNotEmpty())
            {{-- Desktop navigation --}}
            <nav class="hidden items-center gap-6 md:flex">
                @foreach($menu->items->whereNull('parent_id')->sortBy('sort_order') as $item)
                    <a
                        href="{{ $resolveUrl($item) }}"
                        class="text-sm font-medium text-gray-600 transition hover:text-gray-900"
                    >
                        {{ $item->label }}
                    </a>
                @endforeach
            </nav>

            {{-- Mobile menu toggle --}}
            <button
                type="button"
                class="inline-flex items-center justify-center rounded-md p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 md:hidden"
                x-data
                x-on:click="$dispatch('toggle-mobile-menu')"
                aria-label="Toggle menu"
            >
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        @endif
    </div>

    {{-- Mobile navigation --}}
    @if($menu && $menu->items->isNotEmpty())
        <div
            class="border-t border-gray-100 md:hidden"
            x-data="{ open: false }"
            x-on:toggle-mobile-menu.window="open = !open"
            x-show="open"
            x-cloak
        >
            <nav class="space-y-1 px-4 py-3">
                @foreach($menu->items->whereNull('parent_id')->sortBy('sort_order') as $item)
                    <a
                        href="{{ $resolveUrl($item) }}"
                        class="block rounded-md px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50 hover:text-gray-900"
                    >
                        {{ $item->label }}
                    </a>
                @endforeach
            </nav>
        </div>
    @endif
</header>
