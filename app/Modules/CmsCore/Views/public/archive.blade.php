@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $title . ' — ' . $theme->siteName())

@section('seo')
    @include('cms-core::components.seo', [
        'title' => $title . ' — ' . $theme->siteName(),
        'description' => isset($category) ? ($category->description ?? '') : (isset($tag) ? ($tag->description ?? '') : ''),
        'canonical' => url()->current(),
    ])
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        @include('cms-core::components.breadcrumbs', [
            'items' => [
                ['label' => $title],
            ],
        ])
    </div>

    <section class="mx-auto max-w-7xl px-4 pb-16 sm:px-6 lg:px-8">
        <h1 class="mb-8 text-3xl font-bold text-gray-900">{{ $title }}</h1>

        @if(isset($category) && $category->description)
            <p class="mb-8 text-gray-600">{{ $category->description }}</p>
        @endif

        @if($posts->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($posts as $post)
                    @include('cms-core::components.post-card', ['post' => $post])
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @else
            <p class="text-gray-500">No posts found.</p>
        @endif
    </section>
@endsection
