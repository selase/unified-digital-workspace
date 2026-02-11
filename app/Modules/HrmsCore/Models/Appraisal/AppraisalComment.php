<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Models\Appraisal;

use App\Modules\HrmsCore\Models\Concerns\HasHrmsUuid;
use App\Modules\HrmsCore\Models\Employees\Employee;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AppraisalComment model - Comments from any reviewer.
 *
 * @property int $id
 * @property string $uuid
 * @property string $tenant_id
 * @property int $appraisal_id
 * @property int $author_id
 * @property string $comment
 * @property string $type
 * @property bool $is_private
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AppraisalComment extends Model
{
    use BelongsToTenant;
    use HasHrmsUuid;

    public const TYPE_GENERAL = 'general';

    public const TYPE_FEEDBACK = 'feedback';

    public const TYPE_ACTION_ITEM = 'action_item';

    protected $table = 'hrms_appraisal_comments';

    protected $fillable = [
        'tenant_id',
        'appraisal_id',
        'author_id',
        'comment',
        'type',
        'is_private',
    ];

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'general',
        'is_private' => false,
    ];

    /**
     * Get available comment types.
     *
     * @return array<string, string>
     */
    public static function types(): array
    {
        return [
            self::TYPE_GENERAL => 'General',
            self::TYPE_FEEDBACK => 'Feedback',
            self::TYPE_ACTION_ITEM => 'Action Item',
        ];
    }

    /**
     * Get the appraisal this comment belongs to.
     *
     * @return BelongsTo<Appraisal, $this>
     */
    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    /**
     * Get the author of the comment.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'author_id');
    }

    /**
     * Check if this is a general comment.
     */
    public function isGeneral(): bool
    {
        return $this->type === self::TYPE_GENERAL;
    }

    /**
     * Check if this is feedback.
     */
    public function isFeedback(): bool
    {
        return $this->type === self::TYPE_FEEDBACK;
    }

    /**
     * Check if this is an action item.
     */
    public function isActionItem(): bool
    {
        return $this->type === self::TYPE_ACTION_ITEM;
    }

    /**
     * Check if the comment is visible to the employee.
     */
    public function isVisibleToEmployee(): bool
    {
        return ! $this->is_private;
    }

    /**
     * Scope to filter by appraisal.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeForAppraisal($query, int $appraisalId)
    {
        return $query->where('appraisal_id', $appraisalId);
    }

    /**
     * Scope to filter by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to only public comments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePublic($query)
    {
        return $query->where('is_private', false);
    }

    /**
     * Scope to only private comments.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopePrivate($query)
    {
        return $query->where('is_private', true);
    }

    /**
     * Scope to only action items.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeActionItems($query)
    {
        return $query->where('type', self::TYPE_ACTION_ITEM);
    }

    /**
     * Scope to order by created date.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function scopeLatest($query)
    {
        return $query->orderByDesc('created_at');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_private' => 'boolean',
        ];
    }
}
