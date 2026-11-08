<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Records that a rule already notified a recipient about one occurrence, so a
 * daily check cannot send the same alert every day.
 */
final class AlertDelivery extends Model
{
    use HasUuids;

    protected $connection = 'landlord';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['channels' => 'array'];
    }
}
