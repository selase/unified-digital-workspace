<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class ImpersonateUserController extends Controller
{
    public function impersonate($id)
    {
        $user = User::query()->find($id);

        if ($user->id !== ($original = Auth::user()->id)) {
            session()->put('original_user', $original);

            auth()->login($user);

            return to_route('dashboard')->with([
                'status' => 'success',
                'message' => 'You are now logged in as '.$user->displayName(),
            ]);
        }

        return back()
            ->with([
                'status' => 'warning',
                'message' => 'This action is unauthorized. Please contact admin for support.',
            ]);

    }

    public function stopImpersonation()
    {
        $impersonator = session()->get('original_user');

        // check if Impersonator exists in the session
        if ($impersonator) {
            // destroy tenant Id from session
            session()->forget('tenant_id');

            // log in as the impersonator
            auth()->loginUsingId($impersonator);

            // destroy impersonator id from session
            session()->forget('original_user');

            return to_route('dashboard')->with([
                'status' => 'success',
                'message' => 'User impersonation has been stopped successfully',
            ]);
        }

        return back()
            ->with([
                'status' => 'warning',
                'message' => 'This action is unauthorized. Please contact admin for support.',
            ]);

    }
}
