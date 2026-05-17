@props(['post'])

@php
    $videoUrl = $post->meta?->where('key', 'video_url')->first()?->value ?? null;
    $audioUrl = $post->meta?->where('key', 'audio_url')->first()?->value ?? null;
    $posterMediaId = $post->meta?->where('key', 'poster_media_id')->first()?->value ?? null;

    // Unwrap array values (PostMeta casts to array)
    $videoUrl = is_array($videoUrl) ? ($videoUrl[0] ?? null) : $videoUrl;
    $audioUrl = is_array($audioUrl) ? ($audioUrl[0] ?? null) : $audioUrl;
    $posterMediaId = is_array($posterMediaId) ? ($posterMediaId[0] ?? null) : $posterMediaId;

    $videoEmbed = \App\Modules\CmsCore\Support\VideoEmbed::fromUrl($videoUrl);
    $hasMedia = $videoEmbed || $audioUrl;
@endphp

@if($hasMedia)
    <div {{ $attributes->merge(['class' => 'post-embedded-media']) }}>
        @if($videoEmbed)
            <div class="aspect-video w-full overflow-hidden rounded-lg bg-slate-900">
                @if($videoEmbed->usesIframe())
                    <iframe
                        src="{{ $videoEmbed->watchSrc() }}"
                        class="h-full w-full"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen"
                        allowfullscreen
                        loading="lazy"
                    ></iframe>
                @else
                    <video controls class="h-full w-full" preload="metadata">
                        <source src="{{ $videoEmbed->watchSrc() }}">
                    </video>
                @endif
            </div>
        @endif

        @if($audioUrl)
            <div class="mt-4 rounded-lg bg-slate-50 p-4">
                <audio controls class="w-full" preload="metadata">
                    <source src="{{ $audioUrl }}">
                    Your browser does not support the audio element.
                </audio>
            </div>
        @endif
    </div>
@endif
