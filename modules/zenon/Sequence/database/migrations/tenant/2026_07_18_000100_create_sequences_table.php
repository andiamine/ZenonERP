<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            // ALWAYS `company_id ?? 0`, maintained by Models\Sequence's saving hook. A
            // NULL company_id (tenant-wide sequence) duplicates freely under MySQL's
            // NULL-tolerant unique indexes, which would let two "tenant-wide" rows for the
            // same code coexist — fatal for numbering. Folding NULL to 0 in this shadow
            // column makes unique(code, company_scope) a REAL constraint. This module's
            // whole job is numbering correctness, so the guard lives in the schema.
            $table->unsignedBigInteger('company_scope')->default(0);
            $table->string('mask')->default('{seq:5}');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset_period')->default('never'); // never | year | month
            $table->string('current_period')->nullable();      // e.g. '2026' or '2026-07'
            $table->boolean('gapless')->default(true);
            $table->timestamps();

            $table->unique(['code', 'company_scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
