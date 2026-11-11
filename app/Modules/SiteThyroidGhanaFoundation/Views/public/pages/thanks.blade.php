@extends($cmsLayout ?? 'site-thyroid-ghana-foundation::public.layouts.website')

@section('seo-title', $heading . ' — ' . $theme->siteName())

@section('content')
    @include('site-thyroid-ghana-foundation::public.pages._hero', ['crumb' => $crumb, 'title' => $heading, 'intro' => $message])

    <section class="py-16">
        <div class="mx-auto max-w-3xl px-4 text-center sm:px-6 lg:px-8">
            <a href="{{ $cmsUrl->route('home') }}" class="tgf-btn-primary">Back to the home page</a>
        </div>
    </section>
@endsection
