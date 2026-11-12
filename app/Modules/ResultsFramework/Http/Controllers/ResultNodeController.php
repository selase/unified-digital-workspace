<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ResultsFramework\Http\Requests\SaveResultNodeRequest;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Services\ResultChainService;
use App\Services\Tenancy\TenantContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ResultNodeController extends Controller
{
    public function __construct(private ResultChainService $chain, private TenantContext $context) {}

    public function store(SaveResultNodeRequest $request, string $programme): RedirectResponse
    {
        $model = $this->programme($programme);
        $details = $request->validated();

        try {
            $this->chain->add($model, $details['level'], $details['title'], $this->parent($model, $details), $details);
        } catch (DomainException $error) {
            return back()->withInput()->withErrors(['result' => $error->getMessage()]);
        }

        return back()->with('success', 'Result added.');
    }

    public function move(SaveResultNodeRequest $request, string $programme, string $result): RedirectResponse
    {
        $model = $this->programme($programme);
        $node = $this->node($model, $result);
        $details = $request->validated();

        try {
            $this->chain->move($node, $this->parent($model, $details), (int) ($details['sort_order'] ?? 0));
        } catch (DomainException $error) {
            return back()->withErrors(['result' => $error->getMessage()]);
        }

        return back()->with('success', 'Result moved.');
    }

    public function destroy(Request $request, string $programme, string $result): RedirectResponse
    {
        abort_unless($request->user()?->can('results.manage'), 403);

        try {
            $this->chain->remove($this->node($this->programme($programme), $result));
        } catch (DomainException $error) {
            return back()->withErrors(['result' => $error->getMessage()]);
        }

        return back()->with('success', 'Result removed.');
    }

    private function programme(string $uuid): ResultProgramme
    {
        $tenantId = $this->context->activeTenantId();
        abort_unless($tenantId, 403);

        return ResultProgramme::query()->where('tenant_id', $tenantId)->where('uuid', $uuid)->firstOrFail();
    }

    private function node(ResultProgramme $programme, string $uuid): ResultNode
    {
        return $this->chain->nodes($programme)->where('uuid', $uuid)->firstOrFail();
    }

    private function parent(ResultProgramme $programme, array $details): ?ResultNode
    {
        return isset($details['parent_id']) ? $this->chain->nodes($programme)->findOrFail($details['parent_id']) : null;
    }
}
