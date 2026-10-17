<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class PropertyListing extends Model
{
    use BelongsToTenant;

    protected $table = 'property_listings';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'land_parcel_id',
        'title',
        'slug',
        'location',
        'description',
        'price_pesewas',
        'size_dimension',
        'tenure_type',
        'compliance_stage',
        'features_json',
        'is_featured',
        'is_published',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<LandParcel, $this>
     */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class, 'land_parcel_id');
    }

    public function formattedPrice(): string
    {
        return 'GHS '.number_format($this->price_pesewas / 100, 2);
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->title).'-'.mb_strtolower(Str::random(6));
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_pesewas' => 'integer',
            'features_json' => 'array',
            'is_featured' => 'boolean',
            'is_published' => 'boolean',
        ];
    }
}
