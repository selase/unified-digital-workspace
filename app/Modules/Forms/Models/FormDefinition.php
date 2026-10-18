<?php

declare(strict_types=1);

namespace App\Modules\Forms\Models;

use App\Models\User;
use App\Modules\HrmsCore\Models\Organization\Department;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property string $type
 * @property string $target_audience
 * @property int|null $target_department_id
 * @property string $status
 * @property bool $allow_anonymous
 * @property bool $single_response_per_user
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property string $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class FormDefinition extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'form_definitions';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'title',
        'slug',
        'description',
        'type',
        'target_audience',
        'target_department_id',
        'status',
        'allow_anonymous',
        'single_response_per_user',
        'expires_at',
        'created_by',
    ];

    /**
     * @return HasMany<FormField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(FormField::class, 'form_definition_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<FormSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class, 'form_definition_id')->latest();
    }

    /**
     * @return HasMany<PollVote, $this>
     */
    public function pollVotes(): HasMany
    {
        return $this->hasMany(PollVote::class, 'form_definition_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function isAcceptingSubmissions(): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Computes real-time percentage tallies for live polls.
     *
     * @return array<string, array{count: int, percentage: float}>
     */
    public function getPollTally(): array
    {
        $votes = $this->pollVotes()->get();
        $total = $votes->count();

        $tally = [];

        // Prepopulate with declared options from first field if available
        $optionsField = $this->fields()->whereIn('field_type', ['radio', 'select'])->first();
        $availableOptions = ($optionsField instanceof FormField && is_array($optionsField->options_json))
            ? $optionsField->options_json
            : [];

        foreach ($availableOptions as $opt) {
            $tally[$opt] = ['count' => 0, 'percentage' => 0.0];
        }

        foreach ($votes as $vote) {
            $opt = $vote->selected_option;
            if (! isset($tally[$opt])) {
                $tally[$opt] = ['count' => 0, 'percentage' => 0.0];
            }
            $tally[$opt]['count']++;
        }

        if ($total > 0) {
            foreach ($tally as $opt => $data) {
                $tally[$opt]['percentage'] = round(($data['count'] / $total) * 100, 1);
            }
        }

        return $tally;
    }

    protected function casts(): array
    {
        return [
            'allow_anonymous' => 'boolean',
            'single_response_per_user' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }
}
