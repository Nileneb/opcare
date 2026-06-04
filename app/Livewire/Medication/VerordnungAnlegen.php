<?php

namespace App\Livewire\Medication;

use App\Domains\Identity\Support\CurrentTenant;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Medication\Actions\AddSchedule;
use App\Domains\Medication\Actions\AddStock;
use App\Domains\Medication\Actions\CreatePrescription;
use App\Domains\Medication\Actions\GenerateAdministrations;
use App\Domains\Medication\Data\PrescriptionData;
use App\Domains\Medication\Data\ScheduleData;
use App\Domains\Medication\Data\StockData;
use App\Domains\Medication\Enums\AdministrationTimeslot;
use App\Domains\Medication\Enums\ScheduleFrequency;
use App\Domains\Medication\Models\MedProduct;
use App\Domains\Medication\Models\Situation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class VerordnungAnlegen extends Component
{
    #[Locked]
    public Resident $resident;

    public ?int $medProductId = null;

    public string $bhpText = '';

    public ?int $physicianId = null;

    public ?int $situationId = null;

    public bool $beiBedarf = false;

    public string $frequenz = 'taeglich';

    public array $wochentage = [];

    /** Slot-Value => Menge (z. B. ['morgens' => 1, 'abends' => 0.5]) */
    public array $dosis = [];

    public ?float $maxAnzahlTaeglich = null;

    public string $gueltigVon = '';

    public ?string $gueltigBis = null;

    public string $hinweis = '';

    public ?float $bestandMenge = null;

    public ?string $bestandCharge = null;

    public ?string $bestandVerfall = null;

    public int $vorlaufTage = 14;

    public function mount(Resident $resident): void
    {
        abort_unless($this->darfVerordnen(), 403);
        $this->resident = $resident;
        $this->gueltigVon = today()->toDateString();
        $this->dosis = array_fill_keys(
            array_map(fn ($s) => $s->value, AdministrationTimeslot::scheduled()),
            0
        );
    }

    private function darfVerordnen(): bool
    {
        $u = auth()->user();

        return (bool) ($u?->isSuperAdmin() || $u?->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function speichern(
        CreatePrescription $create,
        AddSchedule $addSchedule,
        AddStock $addStock,
        GenerateAdministrations $generate,
    ): void {
        abort_unless($this->darfVerordnen(), 403);

        $tenantId = app(CurrentTenant::class)->id();

        $this->validate([
            'medProductId' => ['nullable', Rule::exists('med_products', 'id')->where('tenant_id', $tenantId)],
            'bhpText' => ['nullable', 'string'],
            // WHY(IDOR-Prevention): physician_id und situation_id tenant-gescopt, sonst Cross-Tenant-Referenz.
            'physicianId' => ['nullable', Rule::exists('physicians', 'id')->where('tenant_id', $tenantId)],
            'situationId' => ['nullable', Rule::exists('situations', 'id')->where('tenant_id', $tenantId)],
            'beiBedarf' => ['boolean'],
            'frequenz' => ['required', 'in:'.implode(',', array_column(ScheduleFrequency::cases(), 'value'))],
            'wochentage' => ['array'],
            'dosis' => ['array'],
            'maxAnzahlTaeglich' => ['nullable', 'numeric', 'min:0'],
            'gueltigVon' => ['required', 'date'],
            'gueltigBis' => ['nullable', 'date', 'after_or_equal:gueltigVon'],
            'hinweis' => ['nullable', 'string'],
            'bestandMenge' => ['nullable', 'numeric', 'min:0'],
            'bestandCharge' => ['nullable', 'string', 'max:120'],
            'bestandVerfall' => ['nullable', 'date'],
            'vorlaufTage' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        // WHY(fachlich): eine Verordnung braucht entweder ein Präparat ODER einen BHP-Freitext.
        if (! $this->medProductId && trim($this->bhpText) === '') {
            $this->addError('medProductId', 'Bitte ein Präparat wählen oder eine BHP-Anweisung eingeben.');

            return;
        }

        DB::transaction(function () use ($create, $addSchedule, $addStock, $generate) {
            $rx = $create->handle(new PrescriptionData(
                resident_id: $this->resident->id,
                created_by: auth()->id(),
                med_product_id: $this->medProductId,
                bhp_text: trim($this->bhpText) ?: null,
                physician_id: $this->physicianId,
                situation_id: $this->situationId,
                bei_bedarf: $this->beiBedarf,
                gueltig_von: $this->gueltigVon,
                gueltig_bis: $this->gueltigBis,
                hinweis: trim($this->hinweis) ?: null,
            ));

            // nur Slots mit Menge > 0 übernehmen
            $dosis = array_filter($this->dosis, fn ($m) => (float) $m > 0);

            $schedule = $addSchedule->handle($rx, new ScheduleData(
                frequenz: $this->frequenz,
                dosis: $dosis,
                wochentage: $this->frequenz === ScheduleFrequency::Woechentlich->value
                    ? array_map('intval', $this->wochentage)
                    : null,
                max_anzahl_taeglich: $this->maxAnzahlTaeglich,
            ));

            if ($this->bestandMenge && $this->medProductId) {
                $einheit = MedProduct::find($this->medProductId)?->tradeForm?->einheit ?? 'Stk';
                $addStock->handle(new StockData(
                    resident_id: $this->resident->id,
                    med_product_id: $this->medProductId,
                    menge: (float) $this->bestandMenge,
                    einheit: $einheit,
                    charge: $this->bestandCharge,
                    verfall_am: $this->bestandVerfall,
                ));
            }

            if (! $this->beiBedarf && $this->frequenz !== ScheduleFrequency::BeiBedarf->value) {
                // WHY: vorlaufTage = Anzahl Tage inklusive Starttag → addDays(N-1) damit der Zeitraum N Tage umfasst.
                $generate->handle(
                    $schedule,
                    $this->gueltigVon,
                    now()->addDays(max(0, $this->vorlaufTage - 1))->toDateString(),
                );
            }
        });

        session()->flash('status', 'Verordnung angelegt.');
        $this->redirectRoute('medikation.verordnungen', ['resident' => $this->resident->id], navigate: true);
    }

    public function render()
    {
        return view('livewire.medication.verordnung-anlegen', [
            'produkte' => MedProduct::orderBy('name')->get(),
            'aerzte' => Physician::orderBy('name')->get(),
            'situationen' => Situation::orderBy('name')->get(),
            'slots' => AdministrationTimeslot::scheduled(),
            'frequenzen' => ScheduleFrequency::cases(),
        ]);
    }
}
