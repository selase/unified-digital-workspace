<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FormField extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'form_fields';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'form_definition_id',
        'label',
        'field_name',
        'field_type',
        'is_required',
        'options_json',
        'sort_order',
    ];

    /**
     * @return BelongsTo<FormDefinition, $this>
     */
    public function formDefinition(): BelongsTo
    {
        return $this->belongsTo(FormDefinition::class, 'form_definition_id');
    }

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'options_json' => 'array',
            'sort_order' => 'integer',
        ];
    }
}
