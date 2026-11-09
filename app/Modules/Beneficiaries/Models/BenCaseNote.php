<?php

declare(strict_types=1);

namespace App\Modules\Beneficiaries\Models;

use Illuminate\Database\Eloquent\Model;

final class BenCaseNote extends Model
{
    protected $table = 'ben_case_notes';

    protected $connection = 'tenant';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean'];
    }
}
