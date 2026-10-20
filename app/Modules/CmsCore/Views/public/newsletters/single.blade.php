@extends($cmsLayout ?? 'cms-core::public.layouts.website')

@section('seo-title', $post->title . ' — ' . $theme->siteName())

@section('content')
    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">
        <a href="{{ $cmsUrl->route('newsletters.archive') }}" class="text-sm underline">Back to newsletters</a>
        <h1 class="mt-6 text-3xl font-bold text-gray-900">{{ $post->title }}</h1>
        @if($post->published_at)
            <time datetime="{{ $post->published_at->toDateString() }}" class="mt-2 block text-sm text-gray-500">{{ $post->published_at->format('d/m/Y') }}</time>
        @endif
        @if($post->body)
            <div class="prose mt-8 max-w-none">{!! $post->body !!}</div>
        @endif
        @foreach($post->media->where('mime_type', 'application/pdf') as $pdf)
            <a href="{{ $pdf->url() }}" class="mt-6 block underline">Download {{ $post->title }} (PDF)</a>
        @endforeach
    </article>
@endsection
