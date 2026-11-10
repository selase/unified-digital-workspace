<?php

declare(strict_types=1);

namespace App\Modules\Volunteers\Services;

use App\Modules\Finance\Services\FinanceSettings;
use App\Modules\Volunteers\Models\VolHour;
use App\Modules\Volunteers\Models\VolVolunteer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use DomainException;

/** A certificate of service built from approved hours only, so it can be handed to the volunteer as proof. */
final class VolunteerCertificatePdf
{
    public function __construct(private readonly FinanceSettings $settings) {}

    public function output(VolVolunteer $volunteer, ?CarbonInterface $from = null, ?CarbonInterface $to = null): string
    {
        $hours = VolHour::query()->where('volunteer_id', $volunteer->id)->where('status', 'approved')
            ->when($from, fn ($query) => $query->whereDate('worked_on', '>=', $from->toDateString()))
            ->when($to, fn ($query) => $query->whereDate('worked_on', '<=', $to->toDateString()))
            ->orderBy('worked_on')->get();

        if ($hours->isEmpty()) {
            throw new DomainException('There are no approved hours to certify yet.');
        }

        return Pdf::loadView('volunteers::pdf.certificate', [
            'volunteer' => $volunteer,
            'settings' => $this->settings->for($volunteer->tenant_id),
            'hours' => $hours,
            'totalMinutes' => (int) $hours->sum('minutes'),
            'first' => $hours->first()->worked_on,
            'last' => $hours->last()->worked_on,
        ])->setPaper('a4')->output();
    }

    public function filename(VolVolunteer $volunteer): string
    {
        return 'Volunteer-certificate-'.str($volunteer->name)->slug().'.pdf';
    }
}
