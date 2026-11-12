<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResultProgramme extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const STATUSES = ['planning', 'active', 'completed', 'closed'];

    protected $table = 'rf_programmes';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'planning'];

    public function indicators(): HasMany
    {
        return $this->hasMany(ResultIndicator::class, 'programme_id');
    }

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }
}
