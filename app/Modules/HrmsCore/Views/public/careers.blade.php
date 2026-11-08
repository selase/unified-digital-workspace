@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', 'Careers — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => 'Careers — ' . $theme->siteName(),
        'description' => 'Open jobs at ' . $theme->siteName() . '.',
        'canonical' => $careersUrl('careers.index'),
        'ogImage' => null,
    ])
@endsection

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
        <h1 class="text-3xl font-bold text-gray-900 sm:text-4xl">Careers</h1>
        <p class="mt-3 text-lg text-gray-600">Join {{ $theme->siteName() }}. These are the roles open right now.</p>

        <div class="mt-8 flex flex-col gap-4">
            @forelse($postings as $posting)
                <a href="{{ $careersUrl('careers.show', ['slug' => $posting->slug]) }}" class="block rounded-xl bg-white p-6 shadow-sm transition hover:shadow-lg">
                    <h2 class="text-xl font-semibold text-gray-900">{{ $posting->title }}</h2>
                    <p class="mt-1 text-sm text-gray-500">
                        @if($posting->closing_date) Apply by {{ $posting->closing_date->format('j F Y') }} @else Open until filled @endif
                    </p>
                    <p class="mt-3 text-gray-600">{{ \Illuminate\Support\Str::limit(strip_tags((string) $posting->description), 180) }}</p>
                </a>
            @empty
                <div class="rounded-xl bg-gray-50 p-8 text-center text-gray-600">There are no open roles at the moment. Please check back soon.</div>
            @endforelse
        </div>
    </section>
@endsection
