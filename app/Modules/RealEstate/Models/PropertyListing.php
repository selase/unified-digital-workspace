<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $tenant_id
 * @property int|null $land_parcel_id
 * @property string $title
 * @property string $slug
 * @property string $location
 * @property string|null $description
 * @property string|null $image_path
 * @property int $price_pesewas
 * @property string|null $size_dimension
 * @property string $tenure_type
 * @property string $compliance_stage
 * @property array<string, mixed>|null $features_json
 * @property bool $is_featured
 * @property bool $is_published
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
final class PropertyListing extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

    protected $table = 'property_listings';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'land_parcel_id',
        'title',
        'slug',
        'location',
        'description',
        'image_path',
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
                $base = Str::slug($model->title);
                $uniqueSlug = null;
                for ($attempt = 0; $attempt < 20; $attempt++) {
                    $candidate = $base.'-'.mb_strtolower(Str::random(6));
                    if (! self::withoutGlobalScopes()->where('slug', $candidate)->exists()) {
                        $uniqueSlug = $candidate;
                        break;
                    }
                }
                $model->slug = $uniqueSlug ?? ($base.'-'.(string) Str::uuid());
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
