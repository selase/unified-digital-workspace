<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Services;

use App\Models\User;
use App\Modules\ResultsFramework\Models\ResultIndicator;
use App\Modules\ResultsFramework\Models\ResultNode;
use App\Modules\ResultsFramework\Models\ResultProgramme;
use App\Modules\ResultsFramework\Support\LogframeRow;
use Illuminate\Support\Collection;

final class LogframeReport
{
    public const HEADINGS = ['Level', 'Result', 'Indicator', 'Baseline', 'Target', 'Current', 'Status', 'Means of verification', 'Assumptions', 'Risks'];

    public function __construct(
        private ResultChainService $chain,
        private IndicatorService $indicators,
        private BreakdownService $breakdowns,
    ) {}

    /** @return list<LogframeRow> */
    public function rows(ResultProgramme $programme, User $viewer): array
    {
        $indicators = $programme->indicators()->where('tenant_id', $programme->tenant_id)
            ->whereNotNull('result_id')->with('tenant')->orderBy('name')->orderBy('id')->get()->groupBy('result_id');

        return $this->flatten($this->chain->tree($programme), $indicators, $viewer, 0);
    }

    public function omissions(ResultProgramme $programme, array $rows): ?string
    {
        $included = array_sum(array_map(fn (LogframeRow $row): int => count($row->indicators), $rows));
        $omitted = $programme->indicators()->where('tenant_id', $programme->tenant_id)->count() - $included;

        return match (true) {
            $omitted < 1 => null,
            $omitted === 1 => '1 programme indicator is omitted because it has no linked result.',
            default => $omitted.' programme indicators are omitted because they have no linked result.',
        };
    }

    /**
     * @param  list<LogframeRow>  $rows
     * @return list<list<string|int|float|null>>
     */
    public function csvRows(array $rows, ?string $omissions = null): array
    {
        $matrix = [self::HEADINGS];
        $hasBreakdowns = false;
        foreach ($rows as $row) {
            foreach ($row->indicators ?: [null] as $indicator) {
                $hasBreakdowns = $hasBreakdowns || ($indicator['breakdowns'] ?? []) !== [];
                $matrix[] = [
                    ucfirst($row->level), $row->title,
                    $indicator['name'] ?? null, $indicator['baseline'] ?? null, $indicator['target'] ?? null,
                    $indicator['current'] ?? null, $indicator['status'] ?? null, $indicator['means_of_verification'] ?? null,
                    $row->assumptions, $row->risks,
                ];
            }
        }

        foreach ([$omissions, $hasBreakdowns ? 'Breakdown details are available in the web and PDF logframe.' : null] as $note) {
            if ($note !== null) {
                $matrix[] = ['Note', $note, ...array_fill(0, count(self::HEADINGS) - 2, null)];
            }
        }

        return $matrix;
    }

    /**
     * @param  iterable<ResultNode>  $nodes
     * @param  Collection<int, Collection<int, ResultIndicator>>  $byResult
     * @return list<LogframeRow>
     */
    private function flatten(iterable $nodes, Collection $byResult, User $viewer, int $depth): array
    {
        $rows = [];
        foreach ($nodes as $node) {
            $entries = [];
            foreach ($byResult->get($node->id, []) as $indicator) {
                $progress = $this->indicators->progress($indicator, $viewer, includePeriods: false);
                $entries[] = [
                    'name' => $indicator->name,
                    'baseline' => $indicator->baseline,
                    'target' => $indicator->target,
                    'current' => $progress['current'] ?? 'Unavailable',
                    'status' => ucfirst(str_replace('_', ' ', $progress['status'])),
                    'means_of_verification' => $indicator->means_of_verification,
                    'breakdowns' => $this->breakdowns->allForIndicator($indicator, $viewer),
                ];
            }

            $rows[] = new LogframeRow($node->level, $depth, $node->title, $entries, $node->assumptions, $node->risks);
            array_push($rows, ...$this->flatten($node->children, $byResult, $viewer, $depth + 1));
        }

        return $rows;
    }
}
