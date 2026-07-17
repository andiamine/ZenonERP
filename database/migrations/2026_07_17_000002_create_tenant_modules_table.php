<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tenant_modules', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 64);
            $table->string('module', 64);
            $table->boolean('enabled')->default(true);
            // Convenience metadata only — the tenant's own migrations table is the
            // authoritative ledger (CLAUDE.md §4).
            $table->string('migrated_version', 32)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'module']);
            $table->index(['module', 'enabled']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('module')->references('alias')->on('modules')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_modules');
    }
};
