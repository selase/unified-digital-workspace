<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReportDefinition extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'data_source',
        'selected_columns',
        'filters',
        'date_column',
        'is_public',
        'created_by_user_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id', 'uuid');
    }

    public function executionLogs(): HasMany
    {
        return $this->hasMany(ReportExecutionLog::class, 'report_definition_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selected_columns' => 'array',
            'filters' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
