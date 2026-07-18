<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stock Sanctum schema. Table only, created here (not via Sanctum's own vendor
     * migration) because zenon/core owns tenant migrations end to end; wiring up actual
     * API token issuance/endpoints is a deliberate later Phase 5 scope decision
     * (CLAUDE.md §8, "personal_access_tokens deliberately not migrated until Phase 5 §9.1
     * API tokens" — this migration IS that step, endpoints are not).
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};
