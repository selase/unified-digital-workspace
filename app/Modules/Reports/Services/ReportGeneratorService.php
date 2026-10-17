<?php

declare(strict_types=1);

namespace App\Modules\Reports\Services;

use App\Models\User;
use App\Modules\Reports\Models\ReportDefinition;
use App\Modules\Reports\Models\ReportExecutionLog;
use DateTimeInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportGeneratorService
{
    private const ALLOWED_OPERATORS = ['=', '!=', '>', '<', '>=', '<=', 'LIKE'];

    public function __construct(
        private readonly ReportDataSourceRegistry $registry
    ) {}

    /**
     * Builds query for report definition.
     */
    public function buildQuery(ReportDefinition $report, User $user, string $tenantId): Builder
    {
        if (! $this->registry->userCanAccessSource($user, $report->data_source)) {
            throw new DomainException("Access denied: You do not have permission to view data from source [{$report->data_source}].");
        }

        $config = $this->registry->getSourceConfig($report->data_source);
        $modelClass = $config['model'];

        /** @var Builder $query */
        $query = $modelClass::query()->where('tenant_id', $tenantId);

        // Date filter
        if ($report->date_column && in_array($report->date_column, $config['date_columns'], true)) {
            $dateCol = $report->date_column;
            // Check if filters have from/to
            if (! empty($report->filters['from_date'])) {
                $query->whereDate($dateCol, '>=', $report->filters['from_date']);
            }
            if (! empty($report->filters['to_date'])) {
                $query->whereDate($dateCol, '<=', $report->filters['to_date']);
            }
        }

        // Custom field filters
        if (! empty($report->filters['rules']) && is_array($report->filters['rules'])) {
            $allowedColumns = array_keys($config['columns']);
            foreach ($report->filters['rules'] as $rule) {
                $field = $rule['field'] ?? null;
                $operator = $rule['operator'] ?? '=';
                $value = $rule['value'] ?? null;

                if (! $field || ! in_array($field, $allowedColumns, true)) {
                    continue;
                }

                if (! in_array($operator, self::ALLOWED_OPERATORS, true)) {
                    $operator = '=';
                }

                if ($value !== null && $value !== '') {
                    if ($operator === 'LIKE') {
                        $query->where($field, 'LIKE', "%{$value}%");
                    } else {
                        $query->where($field, $operator, $value);
                    }
                }
            }
        }

        $selectedCols = $this->registry->filterAllowedColumns($report->data_source, $report->selected_columns);
        if (empty($selectedCols)) {
            $selectedCols = array_keys($config['columns']);
        }

        // Always include id if present in allowed
        if (! in_array('id', $selectedCols, true) && array_key_exists('id', $config['columns'])) {
            $selectedCols[] = 'id';
        }

        return $query->select($selectedCols)->latest('id');
    }

    /**
     * @return array{
     *     headers: array<string, string>,
     *     rows: list<array<string, mixed>>,
     *     total: int
     * }
     */
    public function runPreview(ReportDefinition $report, User $user, string $tenantId, int $limit = 50): array
    {
        $query = $this->buildQuery($report, $user, $tenantId);
        $total = $query->count();
        $records = $query->limit($limit)->get();

        $config = $this->registry->getSourceConfig($report->data_source);
        $selectedCols = $this->registry->filterAllowedColumns($report->data_source, $report->selected_columns);

        $headers = [];
        foreach ($selectedCols as $col) {
            $headers[$col] = $config['columns'][$col] ?? ucfirst(str_replace('_', ' ', $col));
        }

        $rows = [];
        foreach ($records as $record) {
            $row = [];
            foreach ($selectedCols as $col) {
                $val = $record->{$col};
                if ($val instanceof DateTimeInterface) {
                    $val = $val->format('d/m/Y H:i');
                }
                $row[$col] = $val;
            }
            $rows[] = $row;
        }

        // Log execution
        ReportExecutionLog::create([
            'tenant_id' => $tenantId,
            'report_definition_id' => $report->id,
            'user_id' => $user->uuid !== null ? (string) $user->uuid : null,
            'export_format' => 'preview',
            'rows_count' => count($rows),
            'executed_at' => now(),
        ]);

        return [
            'headers' => $headers,
            'rows' => $rows,
            'total' => $total,
        ];
    }

    public function streamCsv(ReportDefinition $report, User $user, string $tenantId): StreamedResponse
    {
        $query = $this->buildQuery($report, $user, $tenantId);
        $config = $this->registry->getSourceConfig($report->data_source);
        $selectedCols = $this->registry->filterAllowedColumns($report->data_source, $report->selected_columns);

        $headers = [];
        foreach ($selectedCols as $col) {
            $headers[$col] = $config['columns'][$col] ?? ucfirst(str_replace('_', ' ', $col));
        }

        $filename = 'report-'.str_replace(' ', '-', mb_strtolower($report->name)).'-'.date('YmdHis').'.csv';

        $rowCount = 0;

        $response = new StreamedResponse(function () use ($query, $selectedCols, $headers, &$rowCount, $report, $tenantId, $user) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // Write CSV BOM for Excel UTF-8 compatibility
            fwrite($handle, "\xEF\xBB\xBF");

            // Write Header
            fputcsv($handle, array_values($headers));

            $query->chunk(200, function ($records) use ($handle, $selectedCols, &$rowCount) {
                foreach ($records as $record) {
                    $row = [];
                    foreach ($selectedCols as $col) {
                        $val = $record->{$col};
                        if ($val instanceof DateTimeInterface) {
                            $val = $val->format('d/m/Y H:i');
                        }
                        $row[] = (string) $val;
                    }
                    fputcsv($handle, $row);
                    $rowCount++;
                }
            });

            fclose($handle);

            // Log execution
            ReportExecutionLog::create([
                'tenant_id' => $tenantId,
                'report_definition_id' => $report->id,
                'user_id' => $user->uuid !== null ? (string) $user->uuid : null,
                'export_format' => 'csv',
                'rows_count' => $rowCount,
                'executed_at' => now(),
            ]);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}\"");

        return $response;
    }
}
