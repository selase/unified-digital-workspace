<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Subscription extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'provider_id',
        'provider_status',
        'provider_plan',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'current_period_end',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
        'current_period_end' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive($query)
    {
        return $query->where('provider_status', 'active');
    }
}
