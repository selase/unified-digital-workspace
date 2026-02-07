<?php

declare(strict_types=1);

namespace App\Modules\IncidentManagement\Models;

use App\Modules\IncidentManagement\Database\Factories\IncidentReporterFactory;
use App\Modules\IncidentManagement\Models\Concerns\HasIncidentUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $organization
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @use HasFactory<IncidentReporterFactory>
 */
final class IncidentReporter extends Model
{
    use BelongsToTenant;
    /** @use HasFactory<IncidentReporterFactory> */
    use HasFactory;

    use HasIncidentUuid;

    protected $table = 'incident_reporters';

    protected $connection = 'landlord';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'name',
        'email',
        'phone',
        'organization',
    ];

    /**
     * @return HasMany<Incident, $this>
     */
    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'reporter_id');
    }

    /**
     * @return Factory<static>
     */
    protected static function newFactory(): Factory
    {
        return IncidentReporterFactory::new();
    }
}
