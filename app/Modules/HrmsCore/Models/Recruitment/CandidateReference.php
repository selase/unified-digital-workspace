<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Recruitment;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CandidateReference model - Reference checks.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $candidate_id
 * @property int|null $application_id
 * @property string $name
 * @property string $relationship
 * @property string|null $company
 * @property string|null $position
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property string|null $feedback
 * @property int|null $rating
 * @property bool|null $is_recommended
 * @property string|null $notes
 * @property int|null $checked_by
 * @property \Illuminate\Support\Carbon|null $contacted_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class CandidateReference extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    protected $table = 'hrms_candidate_references';

    protected $connection = 'landlord';

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'application_id',
        'name',
        'relationship',
        'company',
        'position',
        'email',
        'phone',
        'status',
        'feedback',
        'rating',
        'is_recommended',
        'notes',
        'checked_by',
        'contacted_at',
        'completed_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'is_recommended' => 'boolean',
            'contacted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Candidate, $this>
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    /**
     * @return BelongsTo<CandidateApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function checkedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'checked_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function contact(int $checkedById): bool
    {
        $this->status = 'contacted';
        $this->checked_by = $checkedById;
        $this->contacted_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function complete(string $feedback, int $rating, bool $isRecommended): bool
    {
        $this->status = 'completed';
        $this->feedback = $feedback;
        $this->rating = $rating;
        $this->is_recommended = $isRecommended;
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    public function markNoResponse(): bool
    {
        $this->status = 'no_response';
        $this->completed_at = \Illuminate\Support\Carbon::now();

        return $this->save();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeRecommended($query)
    {
        return $query->where('is_recommended', true);
    }
}
