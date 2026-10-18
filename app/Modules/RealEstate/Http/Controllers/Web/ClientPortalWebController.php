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
        abort_if(! $user || (! $user->can('realestate.client.portal') && ! $user->can('realestate.view')), 403);

        $query = ClientPlotAllocation::query()
            ->where('client_user_id', (string) $user->uuid)
            ->with(['parcel.project', 'parcel.documents', 'installments']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search): void {
                $q->where('allocation_number', 'like', "%{$search}%")
                    ->orWhereHas('parcel', function ($pq) use ($search): void {
                        $pq->where('plot_number', 'like', "%{$search}%")
                            ->orWhereHas('project', function ($prq) use ($search): void {
                                $prq->where('name', 'like', "%{$search}%");
                            });
                    });
            });
        }

        $allocations = $query->latest()->paginate(10)->withQueryString();

        return view('real-estate::portal', [
            'allocations' => $allocations,
            'user' => $user,
        ]);
    }
}
