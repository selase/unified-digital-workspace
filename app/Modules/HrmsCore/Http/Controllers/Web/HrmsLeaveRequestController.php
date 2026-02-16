<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Models\Leave\LeaveRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HrmsLeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.leave.view'), 403);

        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveCategory:id,name'])
            ->latest('id')
            ->paginate(20);

        return view('hrms-core::leave-requests', [
            'leaveRequests' => $leaveRequests,
        ]);
    }
}
