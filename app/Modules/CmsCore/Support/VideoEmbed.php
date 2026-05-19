<?php

declare(strict_types=1);

namespace App\Modules\CmsCore\Support;

final readonly class VideoEmbed
{
    public function __construct(
        public string $kind,
        public string $source,
        public ?string $providerId = null,
    ) {}

    public static function fromUrl(?string $url): ?self
    {
        $url = mb_trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (preg_match('/\.(mp4|webm|ogg)(\?.*)?$/i', $url)) {
            return new self('file', $url);
        }

        if (preg_match('#(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})#', $url, $matches)) {
            return new self('youtube', $url, $matches[1]);
        }

        if (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $url, $matches)) {
            return new self('vimeo', $url, $matches[1]);
        }

        return null;
    }

    public function backgroundSrc(): string
    {
        return match ($this->kind) {
            'youtube' => 'https://www.youtube.com/embed/'.$this->providerId
                .'?autoplay=1&mute=1&loop=1&playlist='.$this->providerId
                .'&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&playsinline=1',
            'vimeo' => 'https://player.vimeo.com/video/'.$this->providerId.'?background=1&autoplay=1&loop=1&muted=1',
            default => $this->source,
        };
    }

    public function watchSrc(): string
    {
        return match ($this->kind) {
            'youtube' => 'https://www.youtube-nocookie.com/embed/'.$this->providerId,
            'vimeo' => 'https://player.vimeo.com/video/'.$this->providerId,
            default => $this->source,
        };
    }

    public function usesIframe(): bool
    {
        return in_array($this->kind, ['youtube', 'vimeo'], true);
    }
}
