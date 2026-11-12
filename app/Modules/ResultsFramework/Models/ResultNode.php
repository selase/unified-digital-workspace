<?php

declare(strict_types=1);

namespace App\Modules\ResultsFramework\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ResultNode extends Model
{
    use BelongsToTenant;
    use HasUuid;

    public const LEVELS = ['goal' => 0, 'outcome' => 1, 'output' => 2, 'activity' => 3];

    protected $table = 'rf_results';

    protected $guarded = ['id'];

    protected $attributes = ['sort_order' => 0];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(ResultIndicator::class, 'result_id');
    }
}
