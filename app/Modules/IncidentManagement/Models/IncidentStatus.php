<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Modules\IncidentManagement\Database\Factories\IncidentStatusFactory;
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
 * @property int $sort_order
 * @property bool $is_terminal
 * @property bool $is_default
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<IncidentStatusFactory>
 */
final class IncidentStatus extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<IncidentStatusFactory> */
    use HasFactory;

    use HasIncidentUuid;

    protected $table = 'incident_statuses';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'sort_order',
        'is_terminal',
        'is_default',
    ];

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'status_id');
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
        return IncidentStatusFactory::new();
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_terminal' => 'boolean',
            'is_default' => 'boolean',
        ];
    }
}
