<?php

namespace App\Domains\Speech\Data;

use App\Domains\CarePlanning\Enums\SisTopicField;
use Spatie\LaravelData\Attributes\Validation\In;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class SisVorschlagFieldData extends Data
{
    public function __construct(
        #[Required, In(['kognition', 'mobilitaet', 'krankheitsbezogen', 'selbstversorgung', 'soziale_beziehungen', 'wohnen'])]
        public string $themenfeld,
        #[Required]
        public string $freitext,
    ) {}

    public function topicField(): SisTopicField
    {
        return SisTopicField::from($this->themenfeld);
    }
}
