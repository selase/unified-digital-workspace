<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Transaction extends Model
{
    use HasFactory, HasUuids;

    protected $connection = 'landlord';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'provider',
        'provider_transaction_id',
        'amount',
        'currency',
        'status',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'integer',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }
}
