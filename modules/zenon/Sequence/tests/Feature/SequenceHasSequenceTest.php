<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Sequence\Contracts\SequenceDefinition;
use Modules\Sequence\Services\SequenceRegistry;
use Tests\Fixtures\Sequence\NumberedDocument;

it('auto-fills the sequence column on create and respects an explicit value', function () {
    $tenant = bootSequenceTenant();

    $tenant->run(function () {
        Schema::create('numbered_documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('title')->nullable();
        });

        app(SequenceRegistry::class)->define(new SequenceDefinition('doc', 'DOC-{seq:4}'));

        $alpha = NumberedDocument::create(['title' => 'Alpha']);
        $bravo = NumberedDocument::create(['title' => 'Bravo']);

        expect($alpha->number)->toBe('DOC-0001')
            ->and($bravo->number)->toBe('DOC-0002');

        // An explicitly-supplied number is left untouched — only an empty column is filled.
        $charlie = NumberedDocument::create(['title' => 'Charlie', 'number' => 'MANUAL-1']);
        expect($charlie->number)->toBe('MANUAL-1');
    });
});
