<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Poison hook for the upgrade failure-isolation test: a tenant that has a
        // `dummy_poison` table simulates a broken migration — no runtime file mutation.
        if (Schema::hasTable('dummy_poison')) {
            throw new RuntimeException('poisoned tenant');
        }

        Schema::create('dummy_item_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dummy_item_id')->index();
            $table->text('note');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dummy_item_notes');
    }
};
