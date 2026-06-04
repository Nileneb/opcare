# OPCare — Plan 3: Speech-Workflow (Human-in-the-Loop) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Gesprochene Pflegedokumentation am Bett → lokale Whisper-Transkription → LLM-Strukturierung in SIS®-Themenfelder → menschliche Freigabe → Speicherung als SIS-/Bericht-Eintrag. Audio wird nach Transkription gelöscht.

**Architecture:** Baut auf Plan 1 & 2. Neue Domäne `App\Domains\Speech`. ASR und LLM sind hinter **Adapter-Interfaces** (`AudioTranscriber`, `SisStructurer`) gekapselt — im Test durch Fakes ersetzt, produktiv Whisper-HTTP bzw. Ollama-HTTP. Eine **Job-Kette** mit Status-State-Machine treibt den Prozess; Fortschritt wird per Reverb gebroadcastet. LLM-Output wird gegen ein `SisVorschlagData`-Schema validiert und **niemals** ungeprüft persistiert.

**Tech Stack:** Laravel 12, PHP 8.5, PostgreSQL, Redis-Queue + Horizon, Laravel Reverb, Pest 3. Whisper-Dienst (HTTP), Ollama (`three.linn.games`, HTTP).

**Voraussetzung:** Plan 1 & 2 implementiert (Resident, SisAssessment, CareReport, CreateSisAssessment, CreateCareReport, CurrentTenant).

**Referenz-Spec:** `docs/superpowers/specs/2026-06-04-pflegeplanung-laravel-design.md` (§5).

---

## File Structure (Plan 3)

```
app/Domains/Speech/
├── Enums/TranscriptionStatus.php
├── Models/TranscriptionJob.php
├── Contracts/{AudioTranscriber.php, SisStructurer.php}
├── Services/{WhisperTranscriber.php, OllamaStructurer.php}
├── Testing/{FakeAudioTranscriber.php, FakeSisStructurer.php}
├── Data/{SisVorschlagData.php, SisVorschlagFieldData.php}
├── Actions/{StartTranscription.php, ApproveTranscription.php}
├── Jobs/{TranscribeAudioJob.php, StructureTranscriptJob.php}
├── Events/TranscriptionProgressed.php
└── Providers/SpeechServiceProvider.php
config/speech.php
tests/Feature/Speech/...
```

---

## Task 1: Reverb + Broadcasting installieren

**Files:**
- Modify: `.env`, `config/` (durch Installer), `composer.json`

- [ ] **Step 1: Reverb installieren**

Run:
```bash
php artisan install:broadcasting --reverb
```
Expected: Reverb-Paket installiert, `config/reverb.php`, `config/broadcasting.php`, `routes/channels.php` vorhanden, `BROADCAST_CONNECTION=reverb` in `.env`.

- [ ] **Step 2: Queue auf Redis**

In `.env`:
```
QUEUE_CONNECTION=redis
```
Horizon installieren:
```bash
composer require laravel/horizon
php artisan horizon:install
```

- [ ] **Step 3: Verifizieren**

Run: `php artisan about | grep -i broadcast`
Expected: Broadcasting-Driver = reverb.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "chore(speech): install reverb broadcasting + horizon"
```

---

## Task 2: TranscriptionJob — Status-Enum, Migration, Model

**Files:**
- Create: `app/Domains/Speech/Enums/TranscriptionStatus.php`, `database/migrations/xxxx_create_transcription_jobs_table.php`, `app/Domains/Speech/Models/TranscriptionJob.php`
- Test: `tests/Feature/Speech/TranscriptionJobTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Speech/TranscriptionJobTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('legt einen Transkriptions-Job im Status queued an', function () {
    $resident = Resident::factory()->create();
    $job = TranscriptionJob::create([
        'resident_id' => $resident->id,
        'kontext' => 'mobilitaet',
        'audio_ref' => 'speech/tmp/a.webm',
        'status' => TranscriptionStatus::Queued,
    ]);

    expect($job->status)->toBe(TranscriptionStatus::Queued)
        ->and($job->resident->is($resident))->toBeTrue();
});
```

- [ ] **Step 2: Enum**

`app/Domains/Speech/Enums/TranscriptionStatus.php`:
```php
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
```

- [ ] **Step 3: Migration**

`database/migrations/2026_06_04_000200_create_transcription_jobs_table.php`:
```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transcription_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('kontext');                 // Themenfeld oder 'bericht'
            $table->string('audio_ref')->nullable();   // temp; nach ASR gelöscht
            $table->string('status')->default('queued');
            $table->text('rohtranskript')->nullable();
            $table->jsonb('sis_vorschlag')->nullable();
            $table->text('fehler')->nullable();
            $table->timestamp('freigegeben_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'resident_id', 'status']);
        });
    }
    public function down(): void { Schema::dropIfExists('transcription_jobs'); }
};
```

- [ ] **Step 4: Model**

`app/Domains/Speech/Models/TranscriptionJob.php`:
```php
<?php

namespace App\Domains\Speech\Models;

use App\Domains\Masterdata\Models\Resident;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Support\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionJob extends BaseModel
{
    protected $fillable = [
        'tenant_id', 'resident_id', 'reviewer_id', 'kontext',
        'audio_ref', 'status', 'rohtranskript', 'sis_vorschlag', 'fehler', 'freigegeben_at',
    ];
    protected $casts = [
        'status' => TranscriptionStatus::class,
        'sis_vorschlag' => 'array',
        'freigegeben_at' => 'datetime',
    ];

    public function resident(): BelongsTo { return $this->belongsTo(Resident::class); }
}
```

- [ ] **Step 5: Migrieren + Test grün**

Run:
```bash
php artisan migrate
./vendor/bin/pest tests/Feature/Speech/TranscriptionJobTest.php
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(speech): transcription job model + status enum"
```

---

## Task 3: Adapter-Contracts + Fakes + DTO-Schema

**Files:**
- Create: `app/Domains/Speech/Contracts/{AudioTranscriber,SisStructurer}.php`, `app/Domains/Speech/Testing/{FakeAudioTranscriber,FakeSisStructurer}.php`, `app/Domains/Speech/Data/{SisVorschlagData,SisVorschlagFieldData}.php`
- Test: `tests/Feature/Speech/SisVorschlagSchemaTest.php`

- [ ] **Step 1: Failing test (Schema-Validierung)**

`tests/Feature/Speech/SisVorschlagSchemaTest.php`:
```php
<?php

use App\Domains\Speech\Data\SisVorschlagData;
use Spatie\LaravelData\Exceptions\CannotCreateData;

it('validiert einen wohlgeformten LLM-Vorschlag', function () {
    $vorschlag = SisVorschlagData::from([
        'felder' => [
            ['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.'],
        ],
    ]);

    expect($vorschlag->felder)->toHaveCount(1)
        ->and($vorschlag->felder[0]->themenfeld)->toBe('mobilitaet');
});

it('weist einen Vorschlag mit unbekanntem Themenfeld ab', function () {
    SisVorschlagData::from(['felder' => [['themenfeld' => 'quatsch', 'freitext' => 'x']]])->toArray();
})->throws(\Throwable::class);
```

- [ ] **Step 2: DTOs mit Validierung**

`app/Domains/Speech/Data/SisVorschlagFieldData.php`:
```php
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
```

`app/Domains/Speech/Data/SisVorschlagData.php`:
```php
<?php

namespace App\Domains\Speech\Data;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SisVorschlagData extends Data
{
    public function __construct(
        /** @var DataCollection<int, SisVorschlagFieldData> */
        public DataCollection $felder,
    ) {}
}
```
> `In`-Regel erzwingt die 6 Themenfelder; ungültige LLM-Antworten werfen eine Validierungs-Exception, bevor etwas gespeichert wird.

- [ ] **Step 3: Contracts**

`app/Domains/Speech/Contracts/AudioTranscriber.php`:
```php
<?php

namespace App\Domains\Speech\Contracts;

interface AudioTranscriber
{
    /** Wandelt eine Audiodatei (Pfad auf der 'local'-Disk) in Rohtext. */
    public function transcribe(string $absolutePath): string;
}
```

`app/Domains/Speech/Contracts/SisStructurer.php`:
```php
<?php

namespace App\Domains\Speech\Contracts;

use App\Domains\Speech\Data\SisVorschlagData;

interface SisStructurer
{
    /** Strukturiert Rohtext in einen validierten SIS-Vorschlag. */
    public function structure(string $transcript, string $kontext): SisVorschlagData;
}
```

- [ ] **Step 4: Fakes**

`app/Domains/Speech/Testing/FakeAudioTranscriber.php`:
```php
<?php

namespace App\Domains\Speech\Testing;

use App\Domains\Speech\Contracts\AudioTranscriber;

class FakeAudioTranscriber implements AudioTranscriber
{
    public function __construct(private string $text = 'Frau M. geht heute sicher am Rollator.') {}

    public function transcribe(string $absolutePath): string
    {
        return $this->text;
    }
}
```

`app/Domains/Speech/Testing/FakeSisStructurer.php`:
```php
<?php

namespace App\Domains\Speech\Testing;

use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Data\SisVorschlagData;

class FakeSisStructurer implements SisStructurer
{
    public function structure(string $transcript, string $kontext): SisVorschlagData
    {
        return SisVorschlagData::from([
            'felder' => [
                ['themenfeld' => $kontext === 'bericht' ? 'mobilitaet' : $kontext, 'freitext' => $transcript],
            ],
        ]);
    }
}
```

- [ ] **Step 5: Test grün**

Run: `./vendor/bin/pest tests/Feature/Speech/SisVorschlagSchemaTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add -A && git commit -m "feat(speech): asr/llm contracts, fakes, validated vorschlag dto"
```

---

## Task 4: Reale Adapter + Binding (config-gesteuert)

**Files:**
- Create: `config/speech.php`, `app/Domains/Speech/Services/{WhisperTranscriber,OllamaStructurer}.php`, `app/Domains/Speech/Providers/SpeechServiceProvider.php`
- Modify: `bootstrap/providers.php`
- Test: `tests/Feature/Speech/BindingTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Speech/BindingTest.php`:
```php
<?php

use App\Domains\Speech\Contracts\{AudioTranscriber, SisStructurer};
use App\Domains\Speech\Testing\{FakeAudioTranscriber, FakeSisStructurer};

it('bindet im Test die Fakes', function () {
    app()->instance(AudioTranscriber::class, new FakeAudioTranscriber());
    app()->instance(SisStructurer::class, new FakeSisStructurer());

    expect(app(AudioTranscriber::class))->toBeInstanceOf(FakeAudioTranscriber::class)
        ->and(app(SisStructurer::class))->toBeInstanceOf(FakeSisStructurer::class);
});
```

- [ ] **Step 2: Config**

`config/speech.php`:
```php
<?php

return [
    'whisper' => [
        'url' => env('WHISPER_URL', 'http://127.0.0.1:9000'),
        'model' => env('WHISPER_MODEL', 'large-v3'),
        'timeout' => (int) env('WHISPER_TIMEOUT', 120),
    ],
    'ollama' => [
        'url' => env('OLLAMA_URL', 'https://three.linn.games'),
        'model' => env('OLLAMA_MODEL', 'llama3.1:8b'),
        'timeout' => (int) env('OLLAMA_TIMEOUT', 120),
    ],
];
```

- [ ] **Step 3: WhisperTranscriber**

`app/Domains/Speech/Services/WhisperTranscriber.php`:
```php
<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\AudioTranscriber;
use Illuminate\Support\Facades\Http;

class WhisperTranscriber implements AudioTranscriber
{
    public function transcribe(string $absolutePath): string
    {
        $response = Http::timeout(config('speech.whisper.timeout'))
            ->attach('audio_file', file_get_contents($absolutePath), basename($absolutePath))
            ->post(rtrim(config('speech.whisper.url'), '/') . '/asr', [
                'task' => 'transcribe',
                'language' => 'de',
                'model' => config('speech.whisper.model'),
            ])
            ->throw();

        return trim($response->body());
    }
}
```

- [ ] **Step 4: OllamaStructurer**

`app/Domains/Speech/Services/OllamaStructurer.php`:
```php
<?php

namespace App\Domains\Speech\Services;

use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Data\SisVorschlagData;
use Illuminate\Support\Facades\Http;

class OllamaStructurer implements SisStructurer
{
    public function structure(string $transcript, string $kontext): SisVorschlagData
    {
        $prompt = <<<PROMPT
        Du strukturierst deutsche Pflegedokumentation in SIS-Themenfelder.
        Erlaubte Themenfelder: kognition, mobilitaet, krankheitsbezogen, selbstversorgung, soziale_beziehungen, wohnen.
        Kontext-Hinweis: {$kontext}.
        Gib AUSSCHLIESSLICH JSON zurück im Schema:
        {"felder":[{"themenfeld":"<eines der erlaubten>","freitext":"<text>"}]}
        Transkript: {$transcript}
        PROMPT;

        $response = Http::timeout(config('speech.ollama.timeout'))
            ->post(rtrim(config('speech.ollama.url'), '/') . '/api/generate', [
                'model' => config('speech.ollama.model'),
                'prompt' => $prompt,
                'format' => 'json',
                'stream' => false,
            ])
            ->throw();

        $payload = json_decode($response->json('response'), true, 512, JSON_THROW_ON_ERROR);

        // Validierung erfolgt im DTO (In-Regel auf themenfeld).
        return SisVorschlagData::validateAndCreate($payload);
    }
}
```

- [ ] **Step 5: ServiceProvider (Default-Binding auf echte Adapter)**

`app/Domains/Speech/Providers/SpeechServiceProvider.php`:
```php
<?php

namespace App\Domains\Speech\Providers;

use App\Domains\Speech\Contracts\{AudioTranscriber, SisStructurer};
use App\Domains\Speech\Services\{WhisperTranscriber, OllamaStructurer};
use Illuminate\Support\ServiceProvider;

class SpeechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AudioTranscriber::class, WhisperTranscriber::class);
        $this->app->bind(SisStructurer::class, OllamaStructurer::class);
    }
}
```
In `bootstrap/providers.php` ergänzen:
```php
App\Domains\Speech\Providers\SpeechServiceProvider::class,
```

- [ ] **Step 6: Test grün**

Run: `./vendor/bin/pest tests/Feature/Speech/BindingTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A && git commit -m "feat(speech): whisper + ollama adapters, config, provider binding"
```

---

## Task 5: Broadcasting-Event + Channel-Auth

**Files:**
- Create: `app/Domains/Speech/Events/TranscriptionProgressed.php`
- Modify: `routes/channels.php`
- Test: `tests/Feature/Speech/BroadcastEventTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Speech/BroadcastEventTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Events\TranscriptionProgressed;
use App\Domains\Speech\Models\TranscriptionJob;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('feuert ein Fortschritts-Event mit Job-Status', function () {
    Event::fake([TranscriptionProgressed::class]);
    $resident = Resident::factory()->create();
    $job = TranscriptionJob::create([
        'resident_id' => $resident->id, 'kontext' => 'mobilitaet',
        'audio_ref' => 'x', 'status' => 'queued',
    ]);

    event(new TranscriptionProgressed($job));

    Event::assertDispatched(TranscriptionProgressed::class, fn ($e) => $e->job->is($job));
});
```

- [ ] **Step 2: Event**

`app/Domains/Speech/Events/TranscriptionProgressed.php`:
```php
<?php

namespace App\Domains\Speech\Events;

use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranscriptionProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public TranscriptionJob $job) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("transcription.{$this->job->id}");
    }

    public function broadcastWith(): array
    {
        return [
            'status' => $this->job->status->value,
            'rohtranskript' => $this->job->rohtranskript,
            'sis_vorschlag' => $this->job->sis_vorschlag,
        ];
    }
}
```

- [ ] **Step 3: Channel-Authorisierung**

In `routes/channels.php` ergänzen:
```php
use App\Domains\Speech\Models\TranscriptionJob;

Broadcast::channel('transcription.{jobId}', function ($user, int $jobId) {
    $job = TranscriptionJob::find($jobId);
    return $job !== null && $job->tenant_id === $user->tenant_id;
});
```

- [ ] **Step 4: Test grün**

Run: `./vendor/bin/pest tests/Feature/Speech/BroadcastEventTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(speech): transcription progress event + channel auth"
```

---

## Task 6: StartTranscription-Action (Audio-Upload → Job)

**Files:**
- Create: `app/Domains/Speech/Actions/StartTranscription.php`
- Test: `tests/Feature/Speech/StartTranscriptionTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Speech/StartTranscriptionTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Actions\StartTranscription;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Jobs\TranscribeAudioJob;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Queue, Storage};

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('speichert Audio temporär, legt Job an und dispatcht die Transkription', function () {
    $resident = Resident::factory()->create();
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');

    $job = app(StartTranscription::class)->handle($resident->id, 'mobilitaet', $audio);

    expect($job->status)->toBe(TranscriptionStatus::Queued)
        ->and($job->audio_ref)->not->toBeNull();
    Storage::disk('local')->assertExists($job->audio_ref);
    Queue::assertPushed(TranscribeAudioJob::class, fn ($j) => $j->jobId === $job->id);
});
```

- [ ] **Step 2: Action**

`app/Domains/Speech/Actions/StartTranscription.php`:
```php
<?php

namespace App\Domains\Speech\Actions;

use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Jobs\TranscribeAudioJob;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Http\UploadedFile;

class StartTranscription
{
    public function handle(int $residentId, string $kontext, UploadedFile $audio): TranscriptionJob
    {
        $path = $audio->store('speech/tmp', 'local');

        $job = TranscriptionJob::create([
            'resident_id' => $residentId,
            'kontext' => $kontext,
            'audio_ref' => $path,
            'status' => TranscriptionStatus::Queued,
        ]);

        TranscribeAudioJob::dispatch($job->id);

        return $job;
    }
}
```

- [ ] **Step 3: Test schlägt fehl (Job-Klasse fehlt)**

Run: `./vendor/bin/pest tests/Feature/Speech/StartTranscriptionTest.php`
Expected: FAIL — `TranscribeAudioJob` not found. (Wird in Task 7 erstellt.)

- [ ] **Step 4: Commit (vorbereitend)**

```bash
git add -A && git commit -m "feat(speech): start-transcription action (job folgt)"
```

---

## Task 7: Job-Kette (Transkription → Strukturierung) + Audio-Löschung

**Files:**
- Create: `app/Domains/Speech/Jobs/TranscribeAudioJob.php`, `app/Domains/Speech/Jobs/StructureTranscriptJob.php`
- Test: `tests/Feature/Speech/PipelineTest.php`

- [ ] **Step 1: Failing test (synchron, Fakes)**

`tests/Feature/Speech/PipelineTest.php`:
```php
<?php

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Actions\StartTranscription;
use App\Domains\Speech\Contracts\{AudioTranscriber, SisStructurer};
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Testing\{FakeAudioTranscriber, FakeSisStructurer};
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['queue.default' => 'sync']);
    app()->instance(AudioTranscriber::class, new FakeAudioTranscriber('Frau M. geht am Rollator.'));
    app()->instance(SisStructurer::class, new FakeSisStructurer());
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('durchläuft die Kette bis status review und löscht das Audio', function () {
    $resident = Resident::factory()->create();
    $audio = UploadedFile::fake()->create('note.webm', 50, 'audio/webm');

    $job = app(StartTranscription::class)->handle($resident->id, 'mobilitaet', $audio);
    $job->refresh();

    expect($job->status)->toBe(TranscriptionStatus::Review)
        ->and($job->rohtranskript)->toBe('Frau M. geht am Rollator.')
        ->and($job->sis_vorschlag['felder'][0]['themenfeld'])->toBe('mobilitaet')
        ->and($job->audio_ref)->toBeNull();
    Storage::disk('local')->assertMissing('speech/tmp/'.basename($audio->hashName()));
});
```

- [ ] **Step 2: TranscribeAudioJob**

`app/Domains/Speech/Jobs/TranscribeAudioJob.php`:
```php
<?php

namespace App\Domains\Speech\Jobs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Contracts\AudioTranscriber;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Events\TranscriptionProgressed;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Storage;

class TranscribeAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $jobId) {}

    public function handle(AudioTranscriber $transcriber): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->findOrFail($this->jobId);
        app(CurrentTenant::class)->set(Tenant::findOrFail($job->tenant_id));

        $job->update(['status' => TranscriptionStatus::Transcribing]);
        event(new TranscriptionProgressed($job));

        $text = $transcriber->transcribe(Storage::disk('local')->path($job->audio_ref));

        $job->update(['rohtranskript' => $text, 'status' => TranscriptionStatus::Structuring]);
        event(new TranscriptionProgressed($job));

        StructureTranscriptJob::dispatch($job->id);
    }

    public function failed(\Throwable $e): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->find($this->jobId);
        $job?->update(['status' => TranscriptionStatus::Failed, 'fehler' => $e->getMessage()]);
    }
}
```

- [ ] **Step 3: StructureTranscriptJob (+ Audio-Löschung)**

`app/Domains/Speech/Jobs/StructureTranscriptJob.php`:
```php
<?php

namespace App\Domains\Speech\Jobs;

use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Speech\Contracts\SisStructurer;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Events\TranscriptionProgressed;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Storage;

class StructureTranscriptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $jobId) {}

    public function handle(SisStructurer $structurer): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->findOrFail($this->jobId);
        app(CurrentTenant::class)->set(Tenant::findOrFail($job->tenant_id));

        $vorschlag = $structurer->structure($job->rohtranskript, $job->kontext);

        // Audio löschen (Datensparsamkeit) — Rohtext + Vorschlag reichen ab hier.
        if ($job->audio_ref) {
            Storage::disk('local')->delete($job->audio_ref);
        }

        $job->update([
            'sis_vorschlag' => $vorschlag->toArray(),
            'audio_ref' => null,
            'status' => TranscriptionStatus::Review,
        ]);
        event(new TranscriptionProgressed($job));
    }

    public function failed(\Throwable $e): void
    {
        $job = TranscriptionJob::withoutGlobalScopes()->find($this->jobId);
        $job?->update(['status' => TranscriptionStatus::Failed, 'fehler' => $e->getMessage()]);
    }
}
```
> Jobs setzen den Tenant-Kontext explizit (Queue hat keine Request-Session); Lookups laufen `withoutGlobalScopes`, da der Scope erst nach dem `set` greift.

- [ ] **Step 4: Tests grün (Pipeline + StartTranscription)**

Run:
```bash
./vendor/bin/pest tests/Feature/Speech/PipelineTest.php tests/Feature/Speech/StartTranscriptionTest.php
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A && git commit -m "feat(speech): transcribe→structure job chain, audio deletion, progress events"
```

---

## Task 8: Human-in-the-Loop-Freigabe

**Files:**
- Create: `app/Domains/Speech/Actions/ApproveTranscription.php`
- Test: `tests/Feature/Speech/ApproveTranscriptionTest.php`

- [ ] **Step 1: Failing test**

`tests/Feature/Speech/ApproveTranscriptionTest.php`:
```php
<?php

use App\Domains\Identity\Models\{Tenant, User};
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Speech\Actions\ApproveTranscription;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use App\Domains\Masterdata\Models\Resident;

beforeEach(function () {
    $t = Tenant::create(['name' => 'A', 'slug' => 'a']);
    app(CurrentTenant::class)->set($t);
});

it('übernimmt einen geprüften Vorschlag als SIS und schließt den Job ab', function () {
    $resident = Resident::factory()->create();
    $reviewer = User::factory()->create(['tenant_id' => $resident->tenant_id]);

    $job = TranscriptionJob::create([
        'resident_id' => $resident->id,
        'kontext' => 'mobilitaet',
        'status' => TranscriptionStatus::Review,
        'rohtranskript' => 'Geht am Rollator.',
        'sis_vorschlag' => ['felder' => [['themenfeld' => 'mobilitaet', 'freitext' => 'Geht am Rollator.']]],
    ]);

    $sis = app(ApproveTranscription::class)->handle(
        $job,
        $reviewer->id,
        ['felder' => [['themenfeld' => 'mobilitaet', 'freitext' => 'Geht sicher am Rollator.']]],
    );

    expect($sis)->toBeInstanceOf(SisAssessment::class)
        ->and($sis->topicFields->first()->freitext)->toBe('Geht sicher am Rollator.')
        ->and($job->fresh()->status)->toBe(TranscriptionStatus::Done)
        ->and($job->fresh()->reviewer_id)->toBe($reviewer->id)
        ->and($job->fresh()->freigegeben_at)->not->toBeNull();
});
```

- [ ] **Step 2: Action**

`app/Domains/Speech/Actions/ApproveTranscription.php`:
```php
<?php

namespace App\Domains\Speech\Actions;

use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\CarePlanning\Models\SisAssessment;
use App\Domains\Speech\Enums\TranscriptionStatus;
use App\Domains\Speech\Models\TranscriptionJob;
use Illuminate\Support\Facades\DB;

class ApproveTranscription
{
    public function __construct(private CreateSisAssessment $createSis) {}

    /**
     * @param array{felder: array<int, array{themenfeld:string, freitext:string}>} $korrigiert
     *        Die vom Menschen geprüften/korrigierten Felder.
     */
    public function handle(TranscriptionJob $job, int $reviewerId, array $korrigiert): SisAssessment
    {
        return DB::transaction(function () use ($job, $reviewerId, $korrigiert) {
            $sis = $this->createSis->handle(new SisAssessmentData(
                resident_id: $job->resident_id,
                created_by: $reviewerId,
                erstellt_am: now()->format('Y-m-d'),
                eingangsfrage: null,
                themenfelder: array_map(fn ($f) => [
                    'themenfeld' => $f['themenfeld'],
                    'freitext' => $f['freitext'],
                    'strukturdaten' => null,
                ], $korrigiert['felder']),
            ));

            $job->update([
                'reviewer_id' => $reviewerId,
                'status' => TranscriptionStatus::Done,
                'freigegeben_at' => now(),
            ]);

            return $sis;
        });
    }
}
```
> v1: Freigabe erzeugt eine SIS. Eine Variante „Kontext = bericht → CareReport" kann analog ergänzt werden (eigene Action-Methode), ist aber für den ersten Durchstich nicht nötig (YAGNI).

- [ ] **Step 3: Test grün**

Run: `./vendor/bin/pest tests/Feature/Speech/ApproveTranscriptionTest.php`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "feat(speech): human-in-the-loop approval → sis assessment"
```

---

## Task 9: Gesamtsuite + Abschluss

**Files:** keine neuen — Verifikation.

- [ ] **Step 1: Frisch migrieren + seeden**

Run: `php artisan migrate:fresh --seed`
Expected: ohne Fehler.

- [ ] **Step 2: Gesamte Test-Suite (Plan 1+2+3)**

Run: `./vendor/bin/pest`
Expected: ALLE PASS.

- [ ] **Step 3: Arch-Tests**

Run: `./vendor/bin/pest tests/Arch/LayeringTest.php`
Expected: PASS (Speech-Domäne hängt nicht an `App\Http`).

- [ ] **Step 4: Commit (falls offene Änderungen)**

```bash
git add -A && git commit -m "test(speech): full suite green across all domains" || echo "nothing to commit"
```

---

## Self-Review-Ergebnis (Plan 3)

- **Spec-Abdeckung (§5):** State-Machine `queued→transcribing→structuring→review→done(+failed)` → Enum (Task 2) + Jobs (Task 7). Whisper/Ollama on-prem hinter Adaptern → Tasks 3,4. LLM-Output schema-validiert vor Persistenz → `SisVorschlagData` (Task 3), genutzt in `OllamaStructurer` + Pipeline. Reverb-Progress → Tasks 1,5,7. Audio-Löschung nach ASR → Task 7. Human-in-the-Loop → Task 8. Idempotente, retrybare Jobs (`tries=3`, `failed()`) → Task 7.
- **Platzhalter:** keine — jeder Code-Schritt vollständig; die „Bericht-Variante" der Freigabe ist bewusst als YAGNI-Hinweis markiert, nicht als offener Pflichtteil.
- **Typ-Konsistenz:** `TranscriptionStatus`-Fälle, `AudioTranscriber::transcribe`, `SisStructurer::structure(): SisVorschlagData`, `StartTranscription::handle`, `jobId`-Property der Jobs, `ApproveTranscription::handle`-Signatur durchgängig identisch; nutzt `CreateSisAssessment`/`SisAssessmentData` exakt wie in Plan 2 definiert.

## Gesamt-Reihenfolge der Umsetzung
1. Plan 1 — Fundament + Masterdata
2. Plan 2 — CarePlanning (SIS®)
3. Plan 3 — Speech-Workflow
Danach: Einarbeitung der Designer-Frontend-Vorlage auf die dünne Livewire-Schicht.
