@extends($cmsLayout ?? 'site-lifepartners-platform::public.layouts.website')

@section('title', $page->title . ' — ' . $theme->siteName())

@section('content')
    <h1 class="lpp-visually-hidden">{{ $page->title }}</h1>
    {!! $page->body !!}
    @if ($page->slug === 'contact')
        <section class="container my-5 lpp-contact-details" aria-label="Preview contact placeholders">
            <h2>Contact details — placeholders</h2>
            <p>Preview only. These details will be replaced before launch.</p>
            <dl>
                <dt>Email</dt>
                <dd>[Public email address to be confirmed]</dd>
                <dt>Phone</dt>
                <dd>[Public phone number to be confirmed]</dd>
                <dt>Address</dt>
                <dd>[Office address to be confirmed]</dd>
                <dt>Enquiry recipient</dt>
                <dd>[Recipient to be confirmed]</dd>
            </dl>
            <p>Online enquiries are not available in this preview.</p>
        </section>
    @endif
@endsection
