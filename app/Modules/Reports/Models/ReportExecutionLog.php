<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReportExecutionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'report_definition_id',
        'user_id',
        'export_format',
        'rows_count',
        'executed_at',
    ];

    /**
     * @return BelongsTo<ReportDefinition, $this>
     */
    public function reportDefinition(): BelongsTo
    {
        return $this->belongsTo(ReportDefinition::class, 'report_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'uuid');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'executed_at' => 'datetime',
            'rows_count' => 'integer',
        ];
    }
}
