<?php

use App\Domains\Qdvs\Data\QdvsResidentPackage;
use App\Domains\Qdvs\Engine\Data\CompiledRule;
use App\Domains\Qdvs\Engine\Data\EvaluationContext;
use App\Domains\Qdvs\Engine\Data\RawRule;
use App\Domains\Qdvs\Engine\Enums\SkipReason;
use App\Domains\Qdvs\Engine\Patterns\DataTypeCheckPattern;
use App\Domains\Qdvs\Engine\Patterns\DateComparisonPattern;
use App\Domains\Qdvs\Engine\Patterns\FutureDatePattern;
use App\Domains\Qdvs\Engine\Patterns\KeyValueCheckPattern;
use App\Domains\Qdvs\Engine\Patterns\MandatoryFieldPattern;
use App\Domains\Qdvs\Engine\Patterns\ValueRangePattern;
use App\Domains\Qdvs\Engine\Support\AssertNormalizer;
use App\Domains\Qdvs\Engine\Support\FieldMap;
use Carbon\CarbonImmutable;

function pkg(array $overrides = []): QdvsResidentPackage
{
    return new QdvsResidentPackage(...array_merge([
        'pseudonym' => 'R-42',
        'geburtsjahr' => 1950,
        'geschlecht' => 'm',
        'pflegegrad' => 3,
        'aufnahme_am' => '2023-01-01',
    ], $overrides));
}

function ctx(QdvsResidentPackage $p, string $today = '2026-06-05'): EvaluationContext
{
    return new EvaluationContext($p, new FieldMap, CarbonImmutable::parse($today));
}

function compile(object $pattern, string $rawAssert): CompiledRule|SkipReason|null
{
    $assert = (new AssertNormalizer)->normalize($rawAssert);

    return $pattern->tryCompile($assert, new RawRule('qs_data', 'X', $rawAssert, 'text', 'ERROR'), new FieldMap);
}

it('MANDATORY_FIELD: leeres Pflichtfeld ist ein Verstoß', function () {
    $rule = compile(new MandatoryFieldPattern,
        "not(exists(GEBURTSJAHR/@value)) or string-length(xs:string(GEBURTSJAHR/@value)) = 0 or xs:string(GEBURTSJAHR/@value) = ''");

    expect($rule)->toBeInstanceOf(CompiledRule::class)
        ->and($rule->violated(ctx(pkg(['geburtsjahr' => null]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['geburtsjahr' => 1950]))))->toBeFalse();
});

it('MANDATORY_FIELD: fehlender Pflegegrad (null) verstößt, gesetzter nicht', function () {
    $rule = compile(new MandatoryFieldPattern,
        "not(exists(PFLEGEGRAD/@value)) or string-length(xs:string(PFLEGEGRAD/@value)) = 0 or xs:string(PFLEGEGRAD/@value) = ''");

    expect($rule->violated(ctx(pkg(['pflegegrad' => null]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['pflegegrad' => 3]))))->toBeFalse();
});

it('KEY_VALUE_CHECK: Geburtsmonat außerhalb 1–12 verstößt', function () {
    $rule = compile(new KeyValueCheckPattern,
        "exists(GEBURTSMONAT/@value) and not(xs:string(GEBURTSMONAT/@value) = ('1','2','3','4','5','6','7','8','9','10','11','12'))");

    expect($rule->violated(ctx(pkg(['geburtsmonat' => 13]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['geburtsmonat' => 5]))))->toBeFalse()
        ->and($rule->violated(ctx(pkg(['geburtsmonat' => null]))))->toBeFalse();
});

it('DATA_TYPE_CHECK: Geburtsmonat länger als 2 Stellen verstößt', function () {
    $rule = compile(new DataTypeCheckPattern,
        'exists(GEBURTSMONAT/@value) and (not(GEBURTSMONAT/@value castable as xs:int) or string-length(xs:string(GEBURTSMONAT/@value)) > 2)');

    expect($rule->violated(ctx(pkg(['geburtsmonat' => 100]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['geburtsmonat' => 5]))))->toBeFalse();
});

it('VALUE_RANGE: Geburtsjahr unter 1900 oder in der Zukunft verstößt', function () {
    $rule = compile(new ValueRangePattern,
        'xs:int(xs:string(GEBURTSJAHR/@value)) < 1900 or xs:int(xs:string(GEBURTSJAHR/@value)) > year-from-date(current-date())');

    expect($rule->violated(ctx(pkg(['geburtsjahr' => 1850]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['geburtsjahr' => 3000]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['geburtsjahr' => 1950]))))->toBeFalse();
});

it('VALUE_RANGE: bare Körpergewicht 0–500', function () {
    $rule = compile(new ValueRangePattern, 'KOERPERGEWICHT/@value < 0 or KOERPERGEWICHT/@value > 500');

    expect($rule->violated(ctx(pkg(['gewicht_kg' => 600]))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['gewicht_kg' => 75]))))->toBeFalse();
});

it('FUTURE_DATE: Einzugsdatum in der Zukunft verstößt', function () {
    $rule = compile(new FutureDatePattern, 'EINZUGSDATUM/@value > current-date()');

    expect($rule->violated(ctx(pkg(['aufnahme_am' => '2099-01-01']))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['aufnahme_am' => '2023-01-01']))))->toBeFalse();
});

it('DATE_COMPARISON: Erhebungsdatum vor Einzugsdatum verstößt', function () {
    $rule = compile(new DateComparisonPattern, 'ERHEBUNGSDATUM/@value < EINZUGSDATUM/@value');

    expect($rule->violated(ctx(pkg(['erhebungsdatum' => '2022-12-01', 'aufnahme_am' => '2023-01-01']))))->toBeTrue()
        ->and($rule->violated(ctx(pkg(['erhebungsdatum' => '2023-02-01', 'aufnahme_am' => '2023-01-01']))))->toBeFalse();
});

it('liefert UNMAPPED für ein nicht gemapptes Feld', function () {
    $rule = compile(new FutureDatePattern, 'APOPLEXDATUM/@value > current-date()');

    expect($rule)->toBe(SkipReason::UnmappedField);
});

it('liefert null, wenn das Muster nicht passt', function () {
    expect(compile(new MandatoryFieldPattern, 'EINZUGSDATUM/@value > current-date()'))->toBeNull();
});
