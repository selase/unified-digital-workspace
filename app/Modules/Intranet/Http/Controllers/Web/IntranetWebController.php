<?php

declare(strict_types=1);

namespace App\Modules\Intranet\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\CmsCore\Models\Post;
use App\Modules\DocumentManagement\Models\Document;
use App\Modules\Intranet\Models\IntranetBoardPost;
use App\Modules\Intranet\Models\IntranetEvent;
use App\Modules\Intranet\Models\IntranetMessageBoard;
use App\Services\ModuleManager;
use App\Services\Tenancy\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class IntranetWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('intranet.view') && ! $user?->can('intranet.manage'), 403);

        $tenant = app(TenantContext::class)->getTenant();
        $moduleManager = app(ModuleManager::class);

        // 1. Internal News & Executive Announcements from CMS (Audience: internal_only or departmental)
        $internalPosts = Post::query()
            ->published()
            ->internal()
            ->with(['author:uuid,first_name,last_name', 'featuredMedia'])
            ->latest('published_at')
            ->limit(6)
            ->get();

        // 2. Upcoming Institutional Events
        $upcomingEvents = IntranetEvent::query()
            ->where('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->orderBy('start_time')
            ->limit(6)
            ->get();

        // 3. Message Boards & Community Posts
        $messageBoards = IntranetMessageBoard::query()
            ->with(['posts' => fn ($q) => $q->with('author:uuid,first_name,last_name')->latest()->limit(5)])
            ->orderByDesc('is_pinned')
            ->latest()
            ->get();

        // 4. ISO & Controlled Policies from Document Management
        $controlledPolicies = collect();
        if ($tenant && $moduleManager->isEnabledForTenant('document-management', $tenant)) {
            $controlledPolicies = Document::query()
                ->where('status', 'published')
                ->latest()
                ->limit(6)
                ->get();
        }

        return view('intranet::index', [
            'internalPosts' => $internalPosts,
            'upcomingEvents' => $upcomingEvents,
            'messageBoards' => $messageBoards,
            'controlledPolicies' => $controlledPolicies,
        ]);
    }

    public function storeEvent(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('intranet.events.manage') && ! $user?->can('intranet.manage'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'event_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'location' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(['all_hands', 'training', 'board_meeting', 'social', 'departmental'])],
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

    public function storeBoardPost(Request $request, IntranetMessageBoard $board): RedirectResponse
    {
        $user = $request->user();
        abort_if(! $user?->can('intranet.boards.post') && ! $user?->can('intranet.view'), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'is_announcement' => ['nullable', 'boolean'],
        ]);

        $post = IntranetBoardPost::create([
            'intranet_message_board_id' => $board->id,
            'author_id' => (string) $user->uuid,
            'title' => $validated['title'],
            'content' => $validated['content'],
            'is_announcement' => ! empty($validated['is_announcement']),
        ]);

        return redirect()
            ->route('intranet.index')
            ->with('status', 'success')
            ->with('message', "Message posted to board '{$board->title}'.");
    }
}
