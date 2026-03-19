<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Models\Organization\Department;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HrmsDepartmentDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.departments.view'), 403);

        $search = mb_trim((string) $request->string('search'));
        $status = (string) $request->string('status');

        $departments = Department::query()
            ->withCount('departmentTypes')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status): void {
                $query->where('is_active', $status === 'active');
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('hrms-core::departments', [
            'departments' => $departments,
            'search' => $search,
            'status' => $status,
        ]);
    }
}
