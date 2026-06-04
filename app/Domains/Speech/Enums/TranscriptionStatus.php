<?php

namespace App\Domains\Speech\Enums;

enum TranscriptionStatus: string
{
    case Queued = 'queued';
    case Transcribing = 'transcribing';
    case Structuring = 'structuring';
    case Review = 'review';
    case Done = 'done';
    case Failed = 'failed';
}
