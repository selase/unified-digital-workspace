<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Modules\CmsCore\Models\Post;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * Lists the foundation's events from CMS posts of the "event" type. Each event keeps its date in the
 * "starts_on" meta (Y-m-d) and optionally "location" and "register_url" metas, so editors manage events like any other post.
 */
final class EventsPageController extends PublicPageController
{
    public function show(): View
    {
        $today = CarbonImmutable::today();
        $events = $this->events();

        return view('site-thyroid-ghana-foundation::public.pages.events', [
            'theme' => $this->theme,
            'upcoming' => $events->filter(fn (array $event): bool => $event['date']->greaterThanOrEqualTo($today))->sortBy(fn (array $event): int => $event['date']->getTimestamp())->values(),
            'past' => $events->filter(fn (array $event): bool => $event['date']->lessThan($today))->sortByDesc(fn (array $event): int => $event['date']->getTimestamp())->take(6)->values(),
        ]);
    }

    /**
     * @return Collection<int, array{post: Post, date: CarbonImmutable, location: string|null, registerUrl: string|null}>
     */
    private function events(): Collection
    {
        return Post::query()->forType('event')->published()->publicOnly()->with(['meta', 'featuredMedia'])->get()
            ->map(function (Post $post): ?array {
                $starts = (string) $post->meta->firstWhere('key', 'starts_on')?->value;
                $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts) === 1 ? CarbonImmutable::createFromFormat('Y-m-d', $starts) : false;

                return $date instanceof CarbonImmutable
                    ? ['post' => $post, 'date' => $date->startOfDay(), 'location' => ($post->meta->firstWhere('key', 'location')?->value) ?: null, 'registerUrl' => $this->registerUrl((string) $post->meta->firstWhere('key', 'register_url')?->value)]
                    : null;
            })
            ->filter()
            ->values();
    }

    private function registerUrl(string $url): ?string
    {
        return preg_match('#^https?://#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
