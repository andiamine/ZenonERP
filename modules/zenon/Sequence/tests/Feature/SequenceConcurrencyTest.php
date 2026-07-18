<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Sequence\Console\SequenceStressCommand;
use Symfony\Component\Process\Process;

/*
 * TRUE-parallel gapless proof — the only test that actually exercises the row lock.
 *
 * Why OS processes and not the in-process sequential tests: under sqlite (the test driver)
 * `lockForUpdate()` is a no-op AND sqlite serialises writers wholesale, so a correct row
 * lock and a MISSING one are indistinguishable there. pcntl (fork) is unavailable on
 * Windows. Only concurrent OS processes hammering real InnoDB rows can prove that two
 * allocators never receive the same number — hence this is gated on ZENON_STRESS_DB_* and
 * skipped by default (it never runs in the standard `composer test` gate).
 *
 * Brief-sanctioned simplification: workers run with `--database=stress` (no tenancy). Full
 * per-worker tenant initialisation over the stress connection was disproportionate, and
 * gaplessness is a property of the InnoDB row lock, not of tenant bootstrapping. The stress
 * DB therefore carries only the two tables the counter needs: companies + sequences.
 */
it('allocates 200 contiguous numbers across 4 concurrent OS processes', function () {
    SequenceStressCommand::configureStressConnection('stress');

    // Fresh schema on the stress MariaDB. The counter only needs `companies` to exist for
    // the sequences FK, so build a minimal one inline rather than pulling in Core's full
    // migration (which also FKs a `users` table this standalone DB doesn't carry).
    $sequences = require module_path('Sequence', 'database/migrations/tenant/2026_07_18_000100_create_sequences_table.php');

    $original = config('database.default');
    config(['database.default' => 'stress']);

    try {
        // FK checks off so any leftover state from a prior interrupted run drops cleanly.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        Schema::dropIfExists('sequences');
        Schema::dropIfExists('companies');
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->timestamps();
        });

        $sequences->up();

        // Pre-materialise the counter so no worker races on first-use creation — this test
        // isolates the INCREMENT lock, which materialisation idempotence covers separately.
        DB::table('sequences')->insert([
            'code' => 'so', 'company_id' => null, 'company_scope' => 0, 'mask' => '{seq}',
            'next_number' => 1, 'reset_period' => 'never', 'current_period' => null,
            'gapless' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    } finally {
        config(['database.default' => $original]);
    }

    $env = [
        'APP_ENV' => 'testing',
        'MODULES_STATUSES_FILE' => 'storage/framework/testing/modules_statuses.json',
        'ZENON_STRESS_DB_HOST' => (string) env('ZENON_STRESS_DB_HOST'),
        'ZENON_STRESS_DB_PORT' => (string) env('ZENON_STRESS_DB_PORT', '3306'),
        'ZENON_STRESS_DB_DATABASE' => (string) env('ZENON_STRESS_DB_DATABASE'),
        'ZENON_STRESS_DB_USERNAME' => (string) env('ZENON_STRESS_DB_USERNAME'),
        'ZENON_STRESS_DB_PASSWORD' => (string) env('ZENON_STRESS_DB_PASSWORD'),
    ];

    // Start 4 workers, each drawing 50 numbers → 200 total, then wait for all.
    $workers = [];
    for ($i = 0; $i < 4; $i++) {
        $process = new Process(
            ['php', 'artisan', 'zenon:sequence:stress', 'so', '--count=50', '--database=stress'],
            base_path(),
            $env,
        );
        $process->setTimeout(120);
        $process->start();
        $workers[] = $process;
    }

    $numbers = [];
    foreach ($workers as $process) {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue(
            'Worker failed: '.$process->getErrorOutput().$process->getOutput(),
        );

        foreach (array_filter(explode("\n", trim($process->getOutput())), 'strlen') as $line) {
            $numbers[] = (int) trim($line);
        }
    }

    sort($numbers);

    // Exactly 200 DISTINCT, contiguous values 1..200 — no gaps (rollbacks would gap),
    // no duplicates (a broken lock would dupe).
    expect($numbers)->toHaveCount(200)
        ->and($numbers)->toBe(range(1, 200));
})->skip(
    ! env('ZENON_STRESS_DB_HOST'),
    'Set ZENON_STRESS_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD to run the MariaDB concurrency test.',
);
