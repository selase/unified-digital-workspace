<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Traits\BelongsToTenant;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Model;

/** A payout from the payment gateway to the bank: gross collected, less the gateway's fees, equals what reached the bank. */
final class FinSettlement extends Model
{
    use BelongsToTenant;
    use HasUuid;

    protected $table = 'fin_settlements';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settled_on' => 'date', 'gross' => 'integer', 'fees' => 'integer', 'net' => 'integer'];
    }
}
