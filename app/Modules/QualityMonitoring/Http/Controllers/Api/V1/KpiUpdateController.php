<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\KpiUpdateStoreRequest;
use App\Modules\QualityMonitoring\Models\Kpi;
use App\Modules\QualityMonitoring\Models\KpiUpdate;
use Illuminate\Http\JsonResponse;

final class KpiUpdateController extends Controller
{
    public function store(KpiUpdateStoreRequest $request, Kpi $kpi): JsonResponse
    {
        $update = KpiUpdate::create([
            'kpi_id' => $kpi->id,
            'captured_by_id' => $request->user()?->id,
            ...$request->validated(),
        ]);

        return response()->json($update, 201);
    }
}
