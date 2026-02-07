<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Modules\IncidentManagement\Database\Factories\IncidentCategoryFactory;
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
 * @property string|null $description
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<IncidentCategoryFactory>
 */
final class IncidentCategory extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<IncidentCategoryFactory> */
    use HasFactory;

    use HasIncidentUuid;

    protected $table = 'incident_categories';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'category_id');
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
        return IncidentCategoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
