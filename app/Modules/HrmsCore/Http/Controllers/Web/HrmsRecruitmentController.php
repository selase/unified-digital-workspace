<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Models\Recruitment\JobPosting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HrmsRecruitmentController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.jobs.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $jobPostings = JobPosting::query()
            ->with(['requisition:id,title,department_id,requisition_number,status', 'requisition.department:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('title', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['open', 'closed'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'open');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrms-core::recruitment', [
            'jobPostings' => $jobPostings,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
