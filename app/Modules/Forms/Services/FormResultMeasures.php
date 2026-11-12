<?php

declare(strict_types=1);

namespace App\Modules\Forms\Services;

use App\Modules\Forms\Models\FormDefinition;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\ResultsFramework\Contracts\ProvidesMeasureFilterOptions;
use App\Modules\ResultsFramework\Contracts\ResultMeasureProvider;
use App\Modules\ResultsFramework\Support\HasNoMeasureBreakdowns;
use App\Modules\ResultsFramework\Support\MeasureDefinition;
use App\Modules\ResultsFramework\Support\MeasureQuery;
use DomainException;

final class FormResultMeasures implements ProvidesMeasureFilterOptions, ResultMeasureProvider
{
    use HasNoMeasureBreakdowns;

    public function measures(): array
    {
        return [new MeasureDefinition('forms.submissions', 'Form submissions', 'forms', 'submissions', ['form_id' => []])];
    }

    public function filterOptions(string $tenantId, string $measureKey): array
    {
        return $measureKey === 'forms.submissions'
            ? ['form_id' => FormDefinition::query()->where('tenant_id', $tenantId)->orderBy('title')->pluck('title', 'id')->all()]
            : [];
    }

    public function value(string $tenantId, string $measureKey, MeasureQuery $query): float
    {
        if ($measureKey !== 'forms.submissions') {
            throw new DomainException('Unknown form measure.');
        }

        $submissions = FormSubmission::query()->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $query->from->toDateString())
            ->where('created_at', '<', $query->to->avoidMutation()->addDay()->toDateString());
        if (isset($query->filters['form_id'])) {
            $submissions->where('form_definition_id', $query->filters['form_id']);
        }

        return (float) $submissions->count();
    }
}
