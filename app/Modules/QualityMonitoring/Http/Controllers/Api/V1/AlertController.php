<?php

declare(strict_types=1);

namespace App\Modules\QualityMonitoring\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\QualityMonitoring\Http\Requests\AlertAcknowledgeRequest;
use App\Modules\QualityMonitoring\Models\Alert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_if(! $request->user()?->can('qm.alerts.manage'), 403);

        $alerts = Alert::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->paginate($request->integer('per_page', 15));

        return response()->json($alerts);
    }

    public function acknowledge(AlertAcknowledgeRequest $request, Alert $alert): JsonResponse
    {
        $alert->status = 'acknowledged';
        $alert->metadata = array_merge($alert->metadata ?? [], [
            'notes' => $request->input('notes'),
            'acknowledged_by' => $request->user()?->id,
            'acknowledged_at' => now()->toIso8601String(),
        ]);
        $alert->save();

        return response()->json($alert);
    }
}
