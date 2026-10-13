<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserLoginHistory;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

final class ProfileController extends Controller
{
    private const HISTORY_LIMIT = 50;

    public function index(Request $request, User $user): Factory|View
    {
        $this->ensureMayView($request->user(), $user);

        $breadcrumbs = [
            ['link' => route('dashboard'), 'name' => __('Home')],
            ['name' => __('locale.labels.account')],
            ['name' => __('locale.labels.user')],
        ];

        $loginSessions = UserLoginHistory::query()->where('user_id', $user->id)->latest()->limit(self::HISTORY_LIMIT)->get();
        $activityLogs = Activity::query()->where('causer_id', $user->id)->latest()->limit(self::HISTORY_LIMIT)->get();

        return view('admin.profile.index', ['user' => $user, 'breadcrumbs' => $breadcrumbs, 'loginSessions' => $loginSessions, 'activityLogs' => $activityLogs]);
    }

    /**
     * A profile shows sign-in history and activity, so it is limited to the
     * account owner, platform staff, and user managers of the same organisation.
     */
    private function ensureMayView(User $viewer, User $profile): void
    {
        $isOwnProfile = $viewer->is($profile);
        $isPlatformStaff = $viewer->can('access-superadmin-dashboard');
        $managesSameOrganisation = $viewer->can('core.users.read')
            && $viewer->tenant_id !== null
            && $viewer->tenant_id === $profile->tenant_id;

        abort_unless($isOwnProfile || $isPlatformStaff || $managesSameOrganisation, 403);
    }
}
