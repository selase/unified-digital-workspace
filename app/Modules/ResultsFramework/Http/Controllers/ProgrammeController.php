<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ResultsFramework\Http\Requests\SaveProgrammeRequest;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\LogframeReport;
use App\Modules\ResultsFramework\Services\ProgrammeService;
use App\Modules\ResultsFramework\Services\ResultChainService;
use App\Modules\ResultsFramework\Support\CsvReport;
use App\Services\Tenancy\TenantContext;
use Barryvdh\DomPDF\Facade\Pdf;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProgrammeController extends Controller
{
    public function __construct(private ProgrammeService $programmes, private TenantContext $context) {}

    public function index(Request $request): View
    {
        abort_unless($request->user()?->can('results.view'), 403);

        return view('results-framework::programmes.index', ['programmes' => ResultProgramme::query()->where('tenant_id', $this->tenantId())->withCount('indicators')->orderBy('name')->get()]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()?->can('results.manage'), 403);

        return view('results-framework::programmes.form', [
            'programme' => new ResultProgramme,
            'grants' => $this->programmes->availableGrants($this->tenantId()),
        ]);
    }

    public function store(SaveProgrammeRequest $request): RedirectResponse
    {
        try {
            $programme = $this->programmes->create($this->tenantId(), $request->validated('name'), $request->validated());
        } catch (DomainException $error) {
            return back()->withInput()->withErrors(['programme' => $error->getMessage()]);
        }

        return redirect()->route('results-framework.programmes.show', $programme->uuid);
    }

    public function show(Request $request, string $programme, ResultChainService $chain): View
    {
        abort_unless($request->user()?->can('results.view'), 403);

        $model = $this->find($programme);
        $manager = $request->user()?->can('results.manage');

        return view('results-framework::programmes.show', [
            'programme' => $model->load(['indicators' => fn ($query) => $query->where('tenant_id', $model->tenant_id)]),
            'chain' => $chain->tree($model),
            'resultOptions' => $manager ? $chain->nodes($model)->get() : collect(),
            'projects' => $manager ? $chain->projects($model) : collect(),
        ]);
    }

    public function logframe(Request $request, string $programme, LogframeReport $report): View|Response
    {
        $viewer = $request->user();
        abort_unless($viewer?->can('results.view'), 403);

        $model = $this->find($programme);
        $rows = $report->rows($model, $viewer);
        $name = 'logframe-'.$model->uuid;
        $omissions = $report->omissions($model, $rows);
        $data = ['programme' => $model, 'rows' => $rows, 'headings' => LogframeReport::HEADINGS, 'omissions' => $omissions];

        return match ($request->query('format')) {
            'csv' => CsvReport::download($report->csvRows($rows, $omissions), $name.'.csv'),
            'pdf' => Pdf::loadView('results-framework::pdf.logframe', $data)->setPaper('a4', 'landscape')->download($name.'.pdf'),
            default => view('results-framework::programmes.logframe', $data),
        };
    }

    public function edit(Request $request, string $programme): View
    {
        abort_unless($request->user()?->can('results.manage'), 403);

        $model = $this->find($programme);

        return view('results-framework::programmes.form', [
            'programme' => $model,
            'grants' => $this->programmes->availableGrants($model->tenant_id),
        ]);
    }

    public function update(SaveProgrammeRequest $request, string $programme): RedirectResponse
    {
        try {
            $this->programmes->update($this->find($programme), $request->validated());
        } catch (DomainException $error) {
            return back()->withInput()->withErrors(['programme' => $error->getMessage()]);
        }

        return redirect()->route('results-framework.programmes.show', $programme);
    }

    public function close(Request $request, string $programme): RedirectResponse
    {
        abort_unless($request->user()?->can('results.manage'), 403);
        $this->programmes->close($this->find($programme));

        return back()->with('success', 'Programme closed.');
    }

    private function find(string $uuid): ResultProgramme
    {
        return ResultProgramme::query()->where('tenant_id', $this->tenantId())->where('uuid', $uuid)->firstOrFail();
    }

    private function tenantId(): string
    {
        $id = $this->context->activeTenantId();
        abort_unless($id, 403, 'No active organisation.');

        return $id;
    }
}
