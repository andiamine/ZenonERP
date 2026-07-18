<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidates spatie/laravel-activitylog 4.12's THREE published migration stubs into
 * ONE tenant migration (the package publishes-not-loads its migrations — verified Task 1)
 * so the package's stock Spatie\Activitylog\Models\Activity works unmodified against this
 * table. Column set, nullability and index names are copied verbatim from:
 *   vendor/spatie/laravel-activitylog/database/migrations/create_activity_log_table.php.stub
 *   vendor/spatie/laravel-activitylog/database/migrations/add_event_column_to_activity_log_table.php.stub
 *   vendor/spatie/laravel-activitylog/database/migrations/add_batch_uuid_column_to_activity_log_table.php.stub
 *
 * The stubs build the table with Schema::connection(config('activitylog.database_connection'))
 * — that config is deliberately left null (see config/activitylog.php), so both the stubs
 * and this migration resolve to Laravel's CURRENT default connection, which stancl/tenancy
 * swaps to the tenant DB during tenant context. This migration runs via the module tenant
 * lifecycle (TenantModuleMigrator), never nwidart's auto-discovery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject'); // subject_type, subject_id + composite index "subject"
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer'); // causer_type, causer_id + composite index "causer"
            $table->json('properties')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
