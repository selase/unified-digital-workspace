<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\IndicatorStoreRequest;
use App\Modules\QualityMonitoring\Models\Indicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class IndicatorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('qm.kpis.manage'), 403);

        return response()->json(Indicator::query()->paginate($request->integer('per_page', 15)));
    }

    public function store(IndicatorStoreRequest $request): JsonResponse
    {
        $indicator = Indicator::create($request->validated());

        return response()->json($indicator, 201);
    }
}
