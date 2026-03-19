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

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $leaveRequests = LeaveRequest::query()
            ->with(['employee', 'leaveCategory:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->whereHas('employee', function ($employeeQuery) use ($search): void {
                    $employeeQuery
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrms-core::leave-requests', [
            'leaveRequests' => $leaveRequests,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
