<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasUuid;
use App\Traits\SpatieActivityLogs;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Package extends Model
{
    use HasFactory;
    use HasUuid;
    use SpatieActivityLogs;

    public const BILLING_MODEL_FLAT_RATE = 'flat_rate';

    public const BILLING_MODEL_PER_SEAT = 'per_seat';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'price',
        'price_ghs',
        'interval',
        'billing_model',
        'description',
        'is_active',
        'markup_percentage',
    ];

    protected $keyType = 'string';

    protected $casts = [
        'price' => 'decimal:2',
        'price_ghs' => 'decimal:2',
        'is_active' => 'boolean',
        'markup_percentage' => 'decimal:2',
    ];

    public function priceForCurrency(string $currency): float
    {
        $price = match ($currency) {
            'USD' => $this->price,
            'GHS' => $this->getAttribute('price_ghs'),
            default => throw new DomainException("Unsupported billing currency: {$currency}."),
        };

        if ($price === null) {
            throw new DomainException("Package {$this->name} has no {$currency} price configured.");
        }

        return (float) $price;
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class, 'package_features')
            ->withPivot('value')
            ->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany<UsagePrice, $this>
     */
    public function usagePrices(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(UsagePrice::class, 'target');
    }
}
