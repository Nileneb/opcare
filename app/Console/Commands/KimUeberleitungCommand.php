<?php

namespace App\Console\Commands;

use App\Domains\Fhir\FhirDocumentExporter;
use App\Domains\Identity\Models\Tenant;
use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Kim\Contracts\KimTransport;
use App\Domains\Kim\Data\KimMessage;
use App\Domains\Masterdata\Models\Resident;
use Illuminate\Console\Command;

/**
 * Komponiert den ÜLB-Überleitungsbogen eines Bewohners als KIM-Nachricht an eine Folgeeinrichtung
 * (KIM/KOM-LE, innere Schicht). Der reale S/MIME-Versand ist dormant (Track C) — siehe docs/INBETRIEBNAHME.md.
 */
class KimUeberleitungCommand extends Command
{
    protected $signature = 'kim:ueberleitung {resident? : Bewohner-ID} {--to= : KIM-Adresse der Folgeeinrichtung} {--output= : .eml-Datei statt stdout}';

    protected $description = 'Komponiert den Überleitungsbogen eines Bewohners als KIM-Nachricht';

    public function handle(FhirDocumentExporter $exporter, KimTransport $transport): int
    {
        $id = $this->argument('resident') ?? Resident::withoutGlobalScopes()->orderBy('id')->value('id');
        $resident = $id ? Resident::withoutGlobalScopes()->find($id) : null;

        if (! $resident) {
            $this->error('Kein Bewohner gefunden.');

            return self::FAILURE;
        }
        app(CurrentTenant::class)->set(Tenant::findOrFail($resident->tenant_id));

        $message = new KimMessage(
            from: (string) config('kim.sender_address'),
            to: (string) ($this->option('to') ?: 'folgeeinrichtung@kim.telematik-test'),
            subject: 'Pflegeüberleitung — '.$resident->name,
            dienstkennung: (string) config('kim.dienstkennung'),
            body: 'Anbei der Überleitungsbogen (ÜLB-MIO) zur Pflegeüberleitung.',
            attachmentContent: $exporter->toJson($resident),
            attachmentFilename: 'ueberleitungsbogen-'.$resident->id.'.fhir.json',
        );

        $eml = $transport->send($message);

        if ($path = $this->option('output')) {
            file_put_contents($path, $eml);
            $this->info("KIM-Nachricht (.eml) geschrieben: {$path}");
        } else {
            $this->line($eml);
        }

        return self::SUCCESS;
    }
}
