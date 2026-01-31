<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\UsageMetric;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsageRollup extends Model
{
    use HasFactory;

    protected $connection = 'landlord';

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'datetime',
        'metric' => UsageMetric::class,
        'dimensions' => 'array',
        'value' => 'decimal:4',
    ];

    protected static function booted()
    {
        static::saving(function ($rollup) {
            $rollup->dimensions_hash = self::hashDimensions($rollup->dimensions);
        });
    }

    public static function hashDimensions(?array $dimensions): string
    {
        if (empty($dimensions)) {
            return 'empty';
        }

        ksort($dimensions);
        return md5(json_encode($dimensions));
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
