<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\RealEstate\Models\ClientPlotAllocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ClientPortalWebController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if(! $user?->can('realestate.client.portal') && ! $user?->can('realestate.view'), 403);

        $allocations = ClientPlotAllocation::query()
            ->where('client_user_id', (string) $user->uuid)
            ->with(['parcel.project', 'parcel.documents', 'installments'])
            ->latest()
            ->get();

        return view('real-estate::portal', [
            'allocations' => $allocations,
            'user' => $user,
        ]);
    }
}
