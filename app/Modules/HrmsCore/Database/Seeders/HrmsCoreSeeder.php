<?php

declare(strict_types=1);

namespace App\Modules\HrmsCore\Database\Seeders;

use Illuminate\Database\Seeder;

final class HrmsCoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            GradeSeeder::class,
            LeaveCategorySeeder::class,
        ]);
    }
}
