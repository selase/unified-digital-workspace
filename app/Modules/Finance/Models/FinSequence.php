<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;

final class FinSequence extends Model
{
    public $timestamps = false;

    protected $table = 'fin_sequences';

    /** @var list<string> */
    protected $guarded = ['id'];

    protected $connection = 'tenant';
}
