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

        $jobPostings = JobPosting::query()
            ->with(['requisition:id,title,department_id,requisition_number,status', 'requisition.department:id,name'])
            ->latest('id')
            ->paginate(20);

        return view('hrms-core::recruitment', [
            'jobPostings' => $jobPostings,
        ]);
    }
}
