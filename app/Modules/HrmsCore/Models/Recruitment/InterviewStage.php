<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * InterviewStage model - Interview workflow stages.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $name
 * @property string|null $description
 * @property int $sequence
 * @property bool $is_mandatory
 * @property bool $is_active
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class InterviewStage extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_interview_stages';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'sequence',
        'is_mandatory',
        'is_active',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sequence' => 0,
        'is_mandatory' => true,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'stage_id');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true);
    }
}
