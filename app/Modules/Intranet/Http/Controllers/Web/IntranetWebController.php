<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\CmsCore\Models\Post;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Modules\Intranet\Models\IntranetBoardPost;
use App\Modules\Intranet\Models\IntranetEvent;
use App\Modules\Intranet\Models\IntranetEventRsvp;
use App\Modules\Intranet\Models\IntranetMessageBoard;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class IntranetWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.view') && ! $user->can('intranet.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $moduleManager = app(ModuleManager::class);

        // 1. Internal News & Executive Announcements from CMS
        $postsQuery = Post::query()
            ->published()
            ->internal()
            ->with(['author:uuid,first_name,last_name', 'featuredMedia'])
            ->latest('published_at');

        if ($request->filled('news_search')) {
            $search = (string) $request->input('news_search');
            $postsQuery->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $internalPosts = $postsQuery->paginate(6, ['*'], 'news_page')->withQueryString();

        // 2. Upcoming Institutional Events with search, category filter, and timeframe filter
        $eventsQuery = IntranetEvent::query()
            ->with(['rsvps.user:uuid,first_name,last_name', 'organizer:uuid,first_name,last_name']);

        $timeframe = (string) $request->input('event_timeframe', 'upcoming');
        if ($timeframe === 'past') {
            $eventsQuery->where('event_date', '<', now()->toDateString())->orderByDesc('event_date');
        } elseif ($timeframe === 'all') {
            $eventsQuery->orderBy('event_date');
        } else {
            $eventsQuery->where('event_date', '>=', now()->toDateString())->orderBy('event_date');
        }

        if ($request->filled('event_category')) {
            $eventsQuery->where('category', (string) $request->input('event_category'));
        }

        if ($request->filled('event_search')) {
            $eventSearch = (string) $request->input('event_search');
            $eventsQuery->where(function (Builder $q) use ($eventSearch): void {
                $q->where('title', 'like', "%{$eventSearch}%")
                    ->orWhere('location', 'like', "%{$eventSearch}%")
                    ->orWhere('description', 'like', "%{$eventSearch}%");
            });
        }

        $upcomingEvents = $eventsQuery->paginate(6, ['*'], 'events_page')->withQueryString();

        // 3. Message Boards with Audience Scoping, Search, and Audience Filter
        $boardsQuery = IntranetMessageBoard::query()
            ->forUser($user)
            ->with([
                'department:id,name',
                'posts' => fn ($q) => $q->topLevel()->with('author:uuid,first_name,last_name')->latest()->limit(3),
            ])
            ->withCount('posts')
            ->orderByDesc('is_pinned')
            ->latest();

        if ($request->filled('board_audience')) {
            $boardsQuery->where('audience', (string) $request->input('board_audience'));
        }

        if ($request->filled('board_search')) {
            $boardSearch = (string) $request->input('board_search');
            $boardsQuery->where(function (Builder $q) use ($boardSearch): void {
                $q->where('title', 'like', "%{$boardSearch}%")
                    ->orWhere('description', 'like', "%{$boardSearch}%");
            });
        }

        $messageBoards = $boardsQuery->paginate(6, ['*'], 'boards_page')->withQueryString();

        // 4. ISO & Controlled Policies from Document Management
        $controlledPolicies = collect();
        if ($tenant && $moduleManager->isEnabledForTenant('document-management', $tenant)) {
            $controlledPolicies = Document::query()
                ->where('status', 'published')
                ->latest()
                ->limit(6)
                ->get();
        }

        // Active departments for board creation
        $departments = collect();
        if (class_exists(Department::class)) {
            $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        }

        return view('intranet::index', [
            'internalPosts' => $internalPosts,
            'upcomingEvents' => $upcomingEvents,
            'messageBoards' => $messageBoards,
            'controlledPolicies' => $controlledPolicies,
            'departments' => $departments,
            'currentUser' => $user,
        ]);
    }

    public function announcements(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.view') && ! $user->can('intranet.manage'), 403);

        $accessibleBoardIds = IntranetMessageBoard::query()->forUser($user)->pluck('id');

        $announcementsQuery = IntranetBoardPost::query()
            ->whereIn('intranet_message_board_id', $accessibleBoardIds)
            ->announcements()
            ->with(['board:id,title,slug,audience', 'author:uuid,first_name,last_name'])
            ->orderByDesc('is_pinned')
            ->latest();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $announcementsQuery->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $announcementsQuery->paginate(10)->withQueryString();

        return view('intranet::announcements', [
            'announcements' => $announcements,
            'currentUser' => $user,
        ]);
    }

    public function storeBoard(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.manage') && ! $user->can('intranet.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', 'string', Rule::in(IntranetMessageBoard::AUDIENCES)],
            'department_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $request->input('audience') === IntranetMessageBoard::AUDIENCE_DEPARTMENTAL),
            ],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $board = IntranetMessageBoard::create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'audience' => $validated['audience'],
            'department_id' => $validated['audience'] === IntranetMessageBoard::AUDIENCE_DEPARTMENTAL ? (int) $validated['department_id'] : null,
            'is_pinned' => ! empty($validated['is_pinned']),
        ]);

        return redirect()
            ->route('intranet.boards.show', $board)
            ->with('status', 'success')
            ->with('message', "Message Board '{$board->title}' created successfully.");
    }

    public function showBoard(Request $request, IntranetMessageBoard $board): View
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.view') && ! $user->can('intranet.manage'), 403);

        // Strict Audience Guard
        abort_if(! $board->isAccessibleBy($user), 403, 'Access restricted to members of the designated department.');

        $postsQuery = $board->posts()
            ->topLevel()
            ->with([
                'author:uuid,first_name,last_name',
                'replies.author:uuid,first_name,last_name',
            ])
            ->orderByDesc('is_pinned')
            ->latest();

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $postsQuery->where(function (Builder $q) use ($search): void {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('announcements_only')) {
            $postsQuery->where('is_announcement', true);
        }

        $posts = $postsQuery->paginate(10)->withQueryString();

        $departments = collect();
        if (class_exists(Department::class)) {
            $departments = Department::query()->where('is_active', true)->orderBy('name')->get();
        }

        return view('intranet::board-show', [
            'board' => $board,
            'posts' => $posts,
            'departments' => $departments,
            'currentUser' => $user,
        ]);
    }

    public function updateBoard(Request $request, IntranetMessageBoard $board): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.manage') && ! $user->can('intranet.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'audience' => ['required', 'string', Rule::in(IntranetMessageBoard::AUDIENCES)],
            'department_id' => [
                'nullable',
                'integer',
                Rule::requiredIf(fn () => $request->input('audience') === IntranetMessageBoard::AUDIENCE_DEPARTMENTAL),
            ],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $board->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'audience' => $validated['audience'],
            'department_id' => $validated['audience'] === IntranetMessageBoard::AUDIENCE_DEPARTMENTAL ? (int) $validated['department_id'] : null,
            'is_pinned' => ! empty($validated['is_pinned']),
        ]);

        return redirect()
            ->route('intranet.boards.show', $board)
            ->with('status', 'success')
            ->with('message', "Message Board '{$board->title}' updated.");
    }

    public function destroyBoard(Request $request, IntranetMessageBoard $board): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.manage') && ! $user->can('intranet.manage'), 403);

        $boardTitle = $board->title;
        $board->delete();

        return redirect()
            ->route('intranet.index')
            ->with('status', 'success')
            ->with('message', "Message Board '{$boardTitle}' deleted.");
    }

    public function storeBoardPost(Request $request, IntranetMessageBoard $board): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.post') && ! $user->can('intranet.view'), 403);

        // Strict Audience Guard
        abort_if(! $board->isAccessibleBy($user), 403, 'Access restricted to members of the designated department.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'is_announcement' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $canPin = $user->can('intranet.boards.manage') || $user->can('intranet.manage');

        $post = IntranetBoardPost::create([
            'intranet_message_board_id' => $board->id,
            'author_id' => (string) $user->uuid,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_announcement' => ! empty($validated['is_announcement']),
            'is_pinned' => $canPin && ! empty($validated['is_pinned']),
        ]);

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', "Message '{$post->title}' posted to board '{$board->title}'.");
    }

    public function updateBoardPost(Request $request, IntranetBoardPost $post): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        // Permission / Party guard: Author or Board Admin
        $canManage = $user->can('intranet.boards.manage') || $user->can('intranet.manage');
        $isAuthor = $post->author_id === (string) $user->uuid;
        abort_if(! $canManage && ! $isAuthor, 403, 'Unauthorized to edit this post.');

        // Audience guard on parent board
        abort_if(! $post->board->isAccessibleBy($user), 403, 'Access restricted to members of the designated department.');

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'is_announcement' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
        ]);

        $updateData = [
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_announcement' => ! empty($validated['is_announcement']),
        ];

        if ($canManage) {
            $updateData['is_pinned'] = ! empty($validated['is_pinned']);
        }

        $post->update($updateData);

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', "Post '{$post->title}' updated.");
    }

    public function destroyBoardPost(Request $request, IntranetBoardPost $post): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);

        // Permission / Party guard: Author or Board Admin
        $canManage = $user->can('intranet.boards.manage') || $user->can('intranet.manage');
        $isAuthor = $post->author_id === (string) $user->uuid;
        abort_if(! $canManage && ! $isAuthor, 403, 'Unauthorized to delete this post.');

        $post->delete();

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', 'Post deleted.');
    }

    public function togglePinBoardPost(Request $request, IntranetBoardPost $post): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.manage') && ! $user->can('intranet.manage'), 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        $statusMsg = $post->is_pinned ? 'pinned to the top' : 'unpinned';

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', "Post '{$post->title}' {$statusMsg}.");
    }

    public function storePostReply(Request $request, IntranetBoardPost $post): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.boards.post') && ! $user->can('intranet.view'), 403);

        // Audience guard on board
        abort_if(! $post->board->isAccessibleBy($user), 403, 'Access restricted to members of the designated department.');

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        IntranetBoardPost::create([
            'intranet_message_board_id' => $post->intranet_message_board_id,
            'parent_id' => $post->id,
            'author_id' => (string) $user->uuid,
            'title' => 'Re: '.$post->title,
            'content' => $validated['content'],
            'is_announcement' => false,
            'is_pinned' => false,
        ]);

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', 'Reply posted.');
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.events.manage') && ! $user->can('intranet.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(IntranetEvent::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_virtual' => ['nullable', 'boolean'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
        ]);

        $event = IntranetEvent::create([
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'organizer_id' => (string) $user->uuid,
            'is_virtual' => ! empty($validated['is_virtual']),
            'meeting_link' => $validated['meeting_link'] ?? null,
        ]);

        return redirect()
            ->route('intranet.index')
            ->with('status', 'success')
            ->with('message', "Intranet Event '{$event->title}' scheduled for {$event->event_date->format('d/m/Y')}.");
    }

    public function updateEvent(Request $request, IntranetEvent $event): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.events.manage') && ! $user->can('intranet.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(IntranetEvent::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_virtual' => ['nullable', 'boolean'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
        ]);

        $event->update([
            'title' => $validated['title'],
            'event_date' => $validated['event_date'],
            'start_time' => $validated['start_time'] ?? null,
            'end_time' => $validated['end_time'] ?? null,
            'location' => $validated['location'],
            'category' => $validated['category'],
            'description' => $validated['description'] ?? null,
            'is_virtual' => ! empty($validated['is_virtual']),
            'meeting_link' => $validated['meeting_link'] ?? null,
        ]);

        return redirect()
            ->route('intranet.index')
            ->with('status', 'success')
            ->with('message', "Event '{$event->title}' updated.");
    }

    public function destroyEvent(Request $request, IntranetEvent $event): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.events.manage') && ! $user->can('intranet.manage'), 403);

        $title = $event->title;
        $event->delete();

        return redirect()
            ->route('intranet.index')
            ->with('status', 'success')
            ->with('message', "Event '{$title}' deleted.");
    }

    public function rsvpEvent(Request $request, IntranetEvent $event): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user instanceof User, 401);
        abort_if(! $user->can('intranet.view') && ! $user->can('intranet.events.rsvp') && ! $user->can('intranet.manage'), 403);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(IntranetEventRsvp::STATUSES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        IntranetEventRsvp::updateOrCreate(
            [
                'intranet_event_id' => $event->id,
                'user_id' => (string) $user->uuid,
            ],
            [
                'status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]
        );

        $statusLabel = match ($validated['status']) {
            IntranetEventRsvp::STATUS_ATTENDING => 'attending',
            IntranetEventRsvp::STATUS_DECLINED => 'declined',
            default => 'tentative',
        };

        return redirect()
            ->back()
            ->with('status', 'success')
            ->with('message', "RSVP recorded as '{$statusLabel}' for '{$event->title}'.");
    }
}
