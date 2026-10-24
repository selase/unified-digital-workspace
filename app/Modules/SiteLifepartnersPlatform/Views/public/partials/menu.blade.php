@foreach($menuItems as $item)
    @php($href = $resolveUrl($item))
    <a href="{{ $href }}"
        @if($item->relationLoaded('post') && $item->post?->slug === 'contact') class="lpp-contact-link" @endif
        @if(rtrim(request()->getPathInfo(), '/') === rtrim(parse_url($href, PHP_URL_PATH) ?? '', '/')) aria-current="page" @endif
    >{{ $item->label }}</a>
@endforeach
