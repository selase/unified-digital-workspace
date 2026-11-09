<?php

declare(strict_types=1);

namespace App\Modules\Donations\Services;

use App\Modules\Donations\Models\DonCampaign;
use App\Modules\Donations\Models\DonDonation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Figures for the donations dashboard. Void gifts never count. */
final class DonationReports
{
    /**
     * @return array{total: int, count: int, donors: int, average: int, by_month: list<array{month: string, total: int}>, by_method: Collection<string, int>}
     */
    public function summary(string $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $gifts = DonDonation::query()->where('tenant_id', $tenantId)->whereNull('voided_at')
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString());

        $total = (int) (clone $gifts)->sum('amount');
        $count = (clone $gifts)->count();

        $byMonth = (clone $gifts)->selectRaw("to_char(date, 'YYYY-MM') as month, SUM(amount) as total")
            ->groupBy('month')->orderBy('month')->pluck('total', 'month');

        $months = [];
        for ($cursor = $from->copy()->startOfMonth(); $cursor->lte($to); $cursor = $cursor->copy()->addMonth()) {
            $key = $cursor->format('Y-m');
            $months[] = ['month' => $cursor->format('M Y'), 'total' => (int) ($byMonth[$key] ?? 0)];
        }

        return [
            'total' => $total,
            'count' => $count,
            'donors' => (clone $gifts)->distinct()->count('donor_id'),
            'average' => $count > 0 ? intdiv($total, $count) : 0,
            'by_month' => $months,
            'by_method' => (clone $gifts)->selectRaw('method, SUM(amount) as total')->groupBy('method')->orderByDesc('total')->pluck('total', 'method')->map(fn ($value): int => (int) $value),
        ];
    }

    /**
     * @return Collection<int, object{donor_id: int, name: string, total: int, gifts: int}>
     */
    public function topDonors(string $tenantId, CarbonInterface $from, CarbonInterface $to, int $limit = 5): Collection
    {
        return DB::connection('tenant')->table('don_donations as d')
            ->join('don_donors as p', 'p.id', '=', 'd.donor_id')
            ->where('d.tenant_id', $tenantId)->whereNull('d.voided_at')
            ->whereDate('d.date', '>=', $from->toDateString())->whereDate('d.date', '<=', $to->toDateString())
            ->groupBy('d.donor_id', 'p.name')
            ->selectRaw('d.donor_id, p.name, SUM(d.amount) as total, COUNT(*) as gifts')
            ->orderByDesc('total')->limit($limit)->get()
            ->map(function (object $row): object {
                $row->total = (int) $row->total;
                $row->gifts = (int) $row->gifts;

                return $row;
            });
    }

    /**
     * Raised against goal for each campaign.
     *
     * @return Collection<int, array{campaign: DonCampaign, raised: int, gifts: int, percent: int}>
     */
    public function campaigns(string $tenantId): Collection
    {
        $raised = DonDonation::query()->where('tenant_id', $tenantId)->whereNull('voided_at')->whereNotNull('campaign_id')
            ->groupBy('campaign_id')->selectRaw('campaign_id, SUM(amount) as total, COUNT(*) as gifts')->get()->keyBy('campaign_id');

        return DonCampaign::query()->where('tenant_id', $tenantId)->with('fund')->orderByDesc('is_active')->orderBy('name')->get()
            ->map(function (DonCampaign $campaign) use ($raised): array {
                $total = (int) ($raised->get($campaign->id)->total ?? 0);

                return [
                    'campaign' => $campaign,
                    'raised' => $total,
                    'gifts' => (int) ($raised->get($campaign->id)->gifts ?? 0),
                    'percent' => $campaign->goal_amount > 0 ? min(100, intdiv($total * 100, $campaign->goal_amount)) : 0,
                ];
            });
    }
}
