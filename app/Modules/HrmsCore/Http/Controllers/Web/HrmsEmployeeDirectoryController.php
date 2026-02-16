<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\HrmsCore\Models\Employees\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class HrmsEmployeeDirectoryController extends Controller
{
    public function index(Request $request): View
    {
        abort_if(! $request->user()?->can('hrms.employees.view'), 403);

        $employees = Employee::query()
            ->with(['grade:id,name', 'center:id,name'])
            ->latest('id')
            ->paginate(20);

        return view('hrms-core::employees', [
            'employees' => $employees,
        ]);
    }
}
