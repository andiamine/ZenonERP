<?php

namespace Modules\Dummy\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyDatabaseSeeder extends Seeder
{
    /**
     * Idempotent by contract (re-runs on enable + upgrade).
     */
    public function run(): void
    {
        DB::table('dummy_items')->updateOrInsert(
            ['label' => 'seed'],
            ['created_at' => now(), 'updated_at' => now()],
        );
    }
}
