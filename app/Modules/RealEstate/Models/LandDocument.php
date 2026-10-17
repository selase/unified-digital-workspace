<?php

declare(strict_types=1);

namespace App\Modules\RealEstate\Models;

use App\Models\User;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class LandDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'land_documents';

    protected $fillable = [
        'uuid',
        'tenant_id',
        'land_parcel_id',
        'workflow_stage',
        'document_type',
        'title',
        'file_path',
        'file_hash',
        'is_legacy',
        'verification_status',
        'uploaded_by_id',
        'verified_by_id',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<LandParcel, $this>
     */
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(LandParcel::class, 'land_parcel_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id', 'uuid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id', 'uuid');
    }

    protected static function booted(): void
    {
        self::creating(function (self $model): void {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_legacy' => 'boolean',
        ];
    }
}
