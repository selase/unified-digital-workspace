<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** A receipt or supplier document kept with a bill or an expense. */
final class FinAttachment extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_attachments';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
