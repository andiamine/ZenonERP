<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->string('name');
            $table->string('symbol')->nullable();
            $table->unsignedTinyInteger('decimal_places')->default(2);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('rate', 20, 10);
            $table->date('valid_from')->index();
            $table->timestamps();

            // Same NULL caveat as settings.company_id (MySQL/MariaDB allow duplicate
            // NULLs in a unique index): this only dedupes COMPANY-specific rates.
            $table->unique(['currency_id', 'company_id', 'valid_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
        Schema::dropIfExists('currencies');
    }
};
