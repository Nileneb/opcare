<?php

use Illuminate\Support\Facades\Schema;

it('legt die Assessment-Tabellen mit den erwarteten Spalten an', function () {
    expect(Schema::hasColumns('instruments', ['tenant_id', 'name', 'risk_type', 'direction', 'risk_bands', 'version', 'superseded_by', 'status']))->toBeTrue()
        ->and(Schema::hasColumns('instrument_items', ['tenant_id', 'instrument_id', 'label', 'reihenfolge']))->toBeTrue()
        ->and(Schema::hasColumns('assessment_options', ['tenant_id', 'instrument_item_id', 'label', 'punkte']))->toBeTrue()
        ->and(Schema::hasColumns('assessments', ['tenant_id', 'resident_id', 'instrument_id', 'score', 'risk_band', 'durchgefuehrt_am', 'faellig_am', 'version', 'superseded_by', 'status', 'created_by']))->toBeTrue()
        ->and(Schema::hasColumns('assessment_answers', ['tenant_id', 'assessment_id', 'instrument_item_id', 'assessment_option_id', 'punkte']))->toBeTrue();
});
