<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Who looked at or changed a beneficiary record, and when. Entries are only ever added. */
final class BenAccessLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'ben_access_log';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
