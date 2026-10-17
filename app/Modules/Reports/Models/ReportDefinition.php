<?php

declare(strict_types=1);

namespace App\Modules\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string $data_source
 * @property array<int, string> $selected_columns
 * @property array<string, mixed>|null $filters
 * @property string|null $date_column
 * @property bool $is_public
 * @property string|null $created_by_user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
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
