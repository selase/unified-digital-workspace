@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $page->title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $page->title . ' — ' . $theme->siteName(),
        'description' => $page->excerpt ?? '',
        'canonical' => $cmsUrl->route('pages.show', $page->slug),
        'ogImage' => $page->featuredMedia
            ? $page->featuredMedia->url()
            : null,
    ])
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('cms-core::components.breadcrumbs', [
            'items' => [
                ['label' => $page->title],
            ],
        ])
    </div>

    {{-- Page header --}}
    <section class="mx-auto max-w-7xl px-4 pb-6 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl">{{ $page->title }}</h1>

        @if($page->excerpt)
            <p class="mt-3 text-lg text-gray-600">{{ $page->excerpt }}</p>
        @endif
    </section>

    {{-- Featured image --}}
    @if($page->featuredMedia)
        <section class="mx-auto max-w-7xl px-4 pb-8 sm:px-6 lg:px-8">
            <img
                src="{{ $page->featuredMedia->url() }}"
                alt="{{ $page->featuredMedia->alt_text ?? $page->title }}"
                class="w-full rounded-lg object-cover"
            />
        </section>
    @endif

    {{-- Page body --}}
    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <div class="prose max-w-none">
            {!! $page->body !!}
        </div>
    </section>

    {{-- Child pages --}}
    @if($page->children->isNotEmpty())
        <section class="border-t border-gray-100 bg-gray-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <h2 class="mb-6 text-xl font-semibold text-gray-900">Related Pages</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($page->children as $child)
                        <a
                            href="{{ $cmsUrl->route('pages.show', $child->slug) }}"
                            class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-100 transition hover:shadow-md"
                        >
                            <h3 class="font-medium text-gray-900">{{ $child->title }}</h3>
                            @if($child->excerpt)
                                <p class="mt-1 text-sm text-gray-500">{{ $child->excerpt }}</p>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
