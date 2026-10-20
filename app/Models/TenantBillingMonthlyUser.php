<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $tenant_id
 * @property int $user_id
 * @property string $level
 * @property \Illuminate\Support\Carbon $month
 */
final class TenantBillingMonthlyUser extends Model
{
    protected $connection = 'landlord';

    protected $fillable = ['tenant_id', 'user_id', 'month', 'level'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['month' => 'date'];
    }
}
