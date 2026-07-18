<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // NULL = tenant-level value (SettingsRepository's fallback base layer).
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            // MySQL/MariaDB permit duplicate NULLs in a unique index, so this constraint
            // alone does not prevent two tenant-level (company_id NULL) rows for the same
            // key. All writes go through Services\SettingsRepository::set()'s
            // updateOrCreate(), which enforces the true one-row-per-(company, key)
            // invariant at the application layer.
            $table->unique(['company_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
