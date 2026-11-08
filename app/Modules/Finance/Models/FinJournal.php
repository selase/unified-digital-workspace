<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class FinJournal extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    protected $table = 'fin_journals';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @return HasMany<FinJournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinJournalLine::class, 'journal_id');
    }

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
