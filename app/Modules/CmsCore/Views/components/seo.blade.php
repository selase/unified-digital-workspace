@props([
    'title' => '',
    'description' => '',
    'ogImage' => null,
    'canonical' => null,
    'type' => 'website',
])

@if($description)
    <meta name="description" content="{{ $description }}" />
@endif

@if($canonical)
    <link rel="canonical" href="{{ $canonical }}" />
@endif

<meta property="og:title" content="{{ $title }}" />
@if($description)
    <meta property="og:description" content="{{ $description }}" />
@endif
<meta property="og:type" content="{{ $type }}" />
@if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}" />
@endif
@if($canonical)
    <meta property="og:url" content="{{ $canonical }}" />
@endif

<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="{{ $title }}" />
@if($description)
    <meta name="twitter:description" content="{{ $description }}" />
@endif
@if($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}" />
@endif
