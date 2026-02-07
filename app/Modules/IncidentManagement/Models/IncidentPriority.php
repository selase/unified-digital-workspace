<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Modules\IncidentManagement\Database\Factories\IncidentPriorityFactory;
use App\Modules\IncidentManagement\Models\Concerns\HasIncidentUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string $slug
 * @property int $level
 * @property int|null $response_time_minutes
 * @property int|null $resolution_time_minutes
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<IncidentPriorityFactory>
 */
final class IncidentPriority extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<IncidentPriorityFactory> */
    use HasFactory;

    use HasIncidentUuid;

    protected $table = 'incident_priorities';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'level',
        'response_time_minutes',
        'resolution_time_minutes',
        'is_active',
    ];

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'priority_id');
    }

    public function setSlugAttribute(string $value): void
    {
        $this->attributes['slug'] = Str::slug($value);
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return IncidentPriorityFactory::new();
    }

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'response_time_minutes' => 'integer',
            'resolution_time_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
