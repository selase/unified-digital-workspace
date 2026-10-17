<?php

declare(strict_types=1);

namespace App\Modules\Crm\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CrmDeal extends Model
{
    use BelongsToTenant;
    use HasUuid;
    use SoftDeletes;

    public const STAGE_DISCOVERY = 'discovery';

    public const STAGE_SITE_VISIT = 'site_visit';

    public const STAGE_PROPOSAL = 'proposal';

    public const STAGE_NEGOTIATION = 'negotiation';

    public const STAGE_CLOSED_WON = 'closed_won';

    public const STAGE_CLOSED_AWAY = 'closed_lost';

    protected $table = 'crm_deals';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'account_id',
        'lead_id',
        'title',
        'stage',
        'currency',
        'value_pesewas',
        'probability',
        'expected_close_date',
        'owner_id',
        'notes',
    ];

    public function getConnectionName(): string
    {
        return config('database.default_tenant_connection', 'tenant');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function formattedValue(): string
    {
        return $this->currency.' '.number_format($this->value_pesewas / 100, 2);
    }

    /**
     * @return BelongsTo<CrmAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<CrmLead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id', 'uuid');
    }

    /**
     * @return MorphMany<CrmActivity, $this>
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(CrmActivity::class, 'subject');
    }

    protected function casts(): array
    {
        return [
            'value_pesewas' => 'integer',
            'probability' => 'integer',
            'expected_close_date' => 'date',
        ];
    }
}
