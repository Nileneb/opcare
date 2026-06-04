<?php

use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Testing\FakeAudioTranscriber;
use App\Domains\Speech\Testing\FakeSisStructurer;

it('bindet im Test die Fakes', function () {
    app()->instance(AudioTranscriber::class, new FakeAudioTranscriber);
    app()->instance(SisStructurer::class, new FakeSisStructurer);

    expect(app(AudioTranscriber::class))->toBeInstanceOf(FakeAudioTranscriber::class)
        ->and(app(SisStructurer::class))->toBeInstanceOf(FakeSisStructurer::class);
});
