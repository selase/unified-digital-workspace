@extends($cmsLayout ?? 'site-lifepartners-platform::public.layouts.website')

@section('title', $theme->siteName())

@section('content')
    @if($homepage)
        @php
            $body = preg_replace(
                '/(<h2\b[^>]*>\s*Success Stories\s*<\/h2>)/iu',
                '$1<p class="lpp-review-note">Source story awaiting verification. Retained for preview review only.</p>',
                $homepage->body,
            ) ?? $homepage->body;
        @endphp
        {!! $body !!}
    @else
        <section class="container lpp-empty-state">
            <h1>{{ $theme->siteName() }}</h1>
            <p>This preview page is not available yet.</p>
        </section>
    @endif
@endsection
