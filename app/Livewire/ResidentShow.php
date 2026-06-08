<?php

namespace App\Livewire;

use App\Domains\CarePlanning\Actions\CreateCareMeasure;
use App\Domains\CarePlanning\Actions\CreateCareReport;
use App\Domains\CarePlanning\Actions\CreateEvaluation;
use App\Domains\CarePlanning\Actions\CreateSisAssessment;
use App\Domains\CarePlanning\Data\CareMeasureData;
use App\Domains\CarePlanning\Data\CareReportData;
use App\Domains\CarePlanning\Data\EvaluationData;
use App\Domains\CarePlanning\Data\SisAssessmentData;
use App\Domains\CarePlanning\Enums\RiskType;
use App\Domains\CarePlanning\Enums\SisTopicField;
use App\Domains\CarePlanning\Models\CareMeasure;
use App\Domains\CarePlanning\Models\MeasureCatalogItem;
use App\Domains\CarePlanning\Support\SisAreaCatalog;
use App\Domains\Masterdata\Models\HealthInsurance;
use App\Domains\Masterdata\Models\IcdCode;
use App\Domains\Masterdata\Models\Physician;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Support\StatusObservationCatalog;
use App\Domains\Personnel\Support\Befugnis;
use App\Domains\Quality\Actions\RecordCareEvent;
use App\Domains\Quality\Data\CareEventData;
use App\Domains\Quality\Enums\EventSeverity;
use App\Domains\Quality\Enums\QualityIndicator;
use App\Domains\Quality\Models\CareEvent;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.app')]
class ResidentShow extends Component
{
    use ScopesTenantValidation;

    #[Locked]
    public Resident $resident;

    // Diagnose
    public ?int $diag_icd = null;

    public ?string $diag_label = null;

    public string $diag_search = '';

    public string $diag_art = 'sekundär';

    // Allergie / Unverträglichkeit (ÜLB-Sektion allergienUndUnvertraeglichkeiten)
    public string $alg_substanz = '';

    public string $alg_typ = 'allergie';

    public string $alg_kategorie = 'medikament';

    public string $alg_kritikalitaet = '';

    public string $alg_reaktion = '';

    // Status-Observation (ÜLB: Bewusstsein/Kontinenz/Ernährung/Atmung)
    public string $so_typ = 'bewusstsein';

    public string $so_wert_code = '';

    public string $so_wert_text = '';

    // Medizinprodukt / Hilfsmittel (ÜLB: medizinprodukte)
    public string $dev_bezeichnung = '';

    public string $dev_kategorie = 'hilfsmittel';

    public string $dev_hinweis = '';

    // Angehörige / Kontaktperson (ÜLB: benachrichtigung / pflegeDurchAngehoerige)
    public string $con_name = '';

    public string $con_beziehung = '';

    public string $con_telefon = '';

    public bool $con_benachrichtigen = false;

    // Versicherung
    public ?int $ins_id = null;

    public string $ins_nr = '';

    public bool $ins_primary = true;

    // Betreuer
    public string $cust_name = '';

    public string $cust_umfang = '';

    public string $cust_kontakt = '';

    // Arzt
    public ?int $phys_id = null;

    // SIS
    public string $sis_eingangsfrage = '';

    public array $sis_felder = [];

    public array $sis_risiken = [];

    // Maßnahme
    public string $m_themenfeld = 'mobilitaet';

    public string $m_beschreibung = '';

    public string $m_katalog_search = '';

    public string $m_ziel = '';

    // Bericht
    public string $r_datum = '';

    public string $r_schicht = 'frueh';

    public string $r_text = '';

    // Vorkommnis / CareEvent
    public string $ce_indicator = 'sturz';

    public string $ce_datum = '';

    public string $ce_severity = '';

    public string $ce_notiz = '';

    // Dekubitus-Detail (nur bei indicator=dekubitus)
    public ?int $ce_dek_stadium = null;

    public string $ce_dek_stelle = '';

    public string $ce_dek_beginn = '';

    public string $ce_dek_ende = '';

    // Sturz-Detail (nur bei indicator=sturz) — DAS-Feld 71/72
    public int $ce_sturz_anzahl = 1;

    public bool $ce_sturz_fraktur = false;

    // Evaluation
    public ?int $e_measure = null;

    public string $e_zielerreichung = 'teilweise';

    public string $e_anlass = '';

    public function mount(Resident $resident): void
    {
        $this->resident = $resident;
        $this->r_datum = now()->format('Y-m-d\TH:i');
        $this->ce_datum = now()->format('Y-m-d');
        foreach (SisTopicField::cases() as $f) {
            $this->sis_felder[$f->value] = '';
        }
    }

    public function recordCareEvent(RecordCareEvent $action): void
    {
        Gate::authorize('create', CareEvent::class);
        $rules = [
            'ce_indicator' => ['required', Rule::enum(QualityIndicator::class)],
            'ce_datum' => ['required', 'date'],
            'ce_severity' => ['nullable', fn ($a, $v, $fail) => $v !== '' && EventSeverity::tryFrom($v) === null ? $fail('Ungültiger Schweregrad.') : null],
            'ce_notiz' => ['nullable', 'string', 'max:1000'],
        ];
        // WHY(DAS_REGELN): Ein Dekubitus ohne Stadium ist eine DAS-Datenlücke (Regel 60019) →
        // Stadium + Beginndatum bei der Erfassung verpflichtend, damit das QDVS-Mapping konsistent ist.
        if ($this->ce_indicator === QualityIndicator::Dekubitus->value) {
            $rules['ce_dek_stadium'] = ['required', 'integer', 'between:1,4'];
            $rules['ce_dek_beginn'] = ['required', 'date'];
            $rules['ce_dek_ende'] = ['nullable', 'date', 'after_or_equal:ce_dek_beginn'];
            $rules['ce_dek_stelle'] = ['nullable', 'string', 'max:120'];
        }
        // WHY(DAS_REGELN): Ein Sturz ist DAS-Feld 71 (1=einmal/2=mehrmals); STURZFOLGEN (Feld 72) ist
        // bei Sturz Pflichtfeld (Regel 60039) — die Fraktur-Angabe füllt es verifiziert (Code 0/1).
        if ($this->ce_indicator === QualityIndicator::Sturz->value) {
            $rules['ce_sturz_anzahl'] = ['required', 'integer', 'between:1,2'];
        }
        $this->validate($rules);

        $action->handle(new CareEventData(
            resident_id: $this->resident->id,
            indicator: $this->ce_indicator,
            datum: $this->ce_datum,
            severity: $this->ce_severity ?: null,
            details: $this->buildDetails(),
        ));

        $this->reset('ce_severity', 'ce_notiz', 'ce_dek_stadium', 'ce_dek_stelle', 'ce_dek_beginn', 'ce_dek_ende', 'ce_sturz_anzahl', 'ce_sturz_fraktur');
        session()->flash('status', 'Vorkommnis dokumentiert.');
    }

    /** @return array<string, mixed>|null */
    private function buildDetails(): ?array
    {
        $details = [];
        if (trim($this->ce_notiz) !== '') {
            $details['notiz'] = $this->ce_notiz;
        }
        if ($this->ce_indicator === QualityIndicator::Dekubitus->value) {
            $details['stadium'] = $this->ce_dek_stadium;
            $details['beginn'] = $this->ce_dek_beginn;
            if ($this->ce_dek_ende !== '') {
                $details['ende'] = $this->ce_dek_ende;
            }
            if (trim($this->ce_dek_stelle) !== '') {
                $details['stelle'] = $this->ce_dek_stelle;
            }
        }
        if ($this->ce_indicator === QualityIndicator::Sturz->value) {
            $details['anzahl'] = $this->ce_sturz_anzahl;
            $details['fraktur'] = $this->ce_sturz_fraktur;
        }

        return $details === [] ? null : $details;
    }

    public function resolveCareEvent(int $id): void
    {
        $event = $this->resident->careEvents()->whereKey($id)->first();
        if (! $event) {
            return;
        }
        Gate::authorize('update', $event);
        if (! $event->behoben_am) {
            $event->update(['behoben_am' => now()->toDateString()]);
        }
    }

    public function selectDiagnosis(int $id): void
    {
        $icd = IcdCode::find($id);
        if (! $icd) {
            return;
        }
        $this->diag_icd = $icd->id;
        $this->diag_label = "{$icd->code} — {$icd->bezeichnung}";
        $this->diag_search = '';
    }

    public function addDiagnosis(): void
    {
        $this->validate(['diag_icd' => ['required', 'exists:icd_codes,id'], 'diag_art' => ['required', 'in:primär,sekundär']]);
        $this->resident->diagnoses()->create(['icd_code_id' => $this->diag_icd, 'art' => $this->diag_art]);
        $this->reset('diag_icd', 'diag_label', 'diag_search', 'diag_art');
        $this->diag_art = 'sekundär';
        session()->flash('status', 'Diagnose hinzugefügt.');
    }

    /** @return Collection<int, IcdCode> */
    private function diagnosisResults(): Collection
    {
        $term = trim($this->diag_search);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        $like = mb_strtolower($term);

        // WHY: LOWER(...) statt LIKE/ILIKE direkt — portabel über SQLite (Tests) und Postgres (Prod)
        return IcdCode::query()
            ->whereRaw('LOWER(code) LIKE ?', [$like.'%'])
            ->orWhereRaw('LOWER(bezeichnung) LIKE ?', ['%'.$like.'%'])
            ->orderBy('code')
            ->limit(25)
            ->get();
    }

    public function addAllergy(): void
    {
        Gate::authorize('update', $this->resident);
        $this->validate([
            'alg_substanz' => ['required', 'string', 'max:255'],
            'alg_typ' => ['required', 'in:allergie,unvertraeglichkeit'],
            'alg_kategorie' => ['required', 'in:medikament,nahrung,umwelt,biologisch'],
            'alg_kritikalitaet' => ['nullable', 'in:niedrig,hoch,unbekannt'],
            'alg_reaktion' => ['nullable', 'string', 'max:255'],
        ]);
        $this->resident->allergies()->create([
            'substanz' => $this->alg_substanz,
            'typ' => $this->alg_typ,
            'kategorie' => $this->alg_kategorie,
            'kritikalitaet' => $this->alg_kritikalitaet ?: null,
            'reaktion' => $this->alg_reaktion ?: null,
            'erfasst_am' => now()->toDateString(),
        ]);
        $this->reset('alg_substanz', 'alg_kritikalitaet', 'alg_reaktion');
        $this->alg_typ = 'allergie';
        $this->alg_kategorie = 'medikament';
        session()->flash('status', 'Allergie/Unverträglichkeit hinzugefügt.');
    }

    public function removeAllergy(int $id): void
    {
        Gate::authorize('update', $this->resident);
        $this->resident->allergies()->whereKey($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function addStatusObservation(): void
    {
        Gate::authorize('update', $this->resident);
        $def = StatusObservationCatalog::get($this->so_typ);
        abort_if($def === null, 422);

        $kind = $def['kind'] ?? 'coded';
        if ($kind === 'coded') {
            $this->validate(['so_wert_code' => ['required', Rule::in(array_keys($def['options']))]]);
            $wert = ['wert_code' => $this->so_wert_code, 'wert_text' => null];
        } elseif ($kind === 'datetime') {
            $this->validate(['so_wert_text' => ['required', 'date']]);
            $wert = ['wert_code' => null, 'wert_text' => $this->so_wert_text];
        } else {
            $this->validate(['so_wert_text' => ['required', 'string', 'max:255']]);
            $wert = ['wert_code' => null, 'wert_text' => $this->so_wert_text];
        }

        $this->resident->statusObservations()->create([
            'typ' => $this->so_typ,
            ...$wert,
            'erfasst_am' => now()->toDateString(),
        ]);
        $this->reset('so_wert_code', 'so_wert_text');
        session()->flash('status', 'Einschätzung dokumentiert.');
    }

    public function removeStatusObservation(int $id): void
    {
        Gate::authorize('update', $this->resident);
        $this->resident->statusObservations()->whereKey($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function addDevice(): void
    {
        Gate::authorize('update', $this->resident);
        $this->validate([
            'dev_bezeichnung' => ['required', 'string', 'max:255'],
            'dev_kategorie' => ['required', 'in:hilfsmittel,implantat,sonstiges'],
            'dev_hinweis' => ['nullable', 'string', 'max:255'],
        ]);
        $this->resident->devices()->create([
            'bezeichnung' => $this->dev_bezeichnung,
            'kategorie' => $this->dev_kategorie,
            'hinweis' => $this->dev_hinweis ?: null,
            'seit' => now()->toDateString(),
        ]);
        $this->reset('dev_bezeichnung', 'dev_hinweis');
        $this->dev_kategorie = 'hilfsmittel';
        session()->flash('status', 'Medizinprodukt hinzugefügt.');
    }

    public function removeDevice(int $id): void
    {
        Gate::authorize('update', $this->resident);
        $this->resident->devices()->whereKey($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function addContact(): void
    {
        Gate::authorize('update', $this->resident);
        $this->validate([
            'con_name' => ['required', 'string', 'max:255'],
            'con_beziehung' => ['nullable', 'string', 'max:80'],
            'con_telefon' => ['nullable', 'string', 'max:60'],
        ]);
        $this->resident->contacts()->create([
            'name' => $this->con_name,
            'beziehung' => $this->con_beziehung ?: null,
            'telefon' => $this->con_telefon ?: null,
            'benachrichtigen' => $this->con_benachrichtigen,
        ]);
        $this->reset('con_name', 'con_beziehung', 'con_telefon', 'con_benachrichtigen');
        session()->flash('status', 'Kontaktperson hinzugefügt.');
    }

    public function removeContact(int $id): void
    {
        Gate::authorize('update', $this->resident);
        $this->resident->contacts()->whereKey($id)->delete();
        session()->flash('status', 'Eintrag entfernt.');
    }

    public function addInsurance(): void
    {
        $this->validate(['ins_id' => ['required', $this->tenantExists('health_insurances')], 'ins_nr' => ['nullable', 'string', 'max:60']]);
        $this->resident->insurances()->create([
            'health_insurance_id' => $this->ins_id,
            'versichertennr' => $this->ins_nr ?: null,
            'ist_primaer' => $this->ins_primary,
        ]);
        $this->reset('ins_id', 'ins_nr');
        $this->ins_primary = true;
        session()->flash('status', 'Versicherung hinzugefügt.');
    }

    public function addCustodian(): void
    {
        $this->validate(['cust_name' => ['required', 'string', 'max:255']]);
        $this->resident->custodians()->create([
            'name' => $this->cust_name,
            'umfang' => $this->cust_umfang ?: null,
            'kontakt' => $this->cust_kontakt ?: null,
        ]);
        $this->reset('cust_name', 'cust_umfang', 'cust_kontakt');
        session()->flash('status', 'Betreuer:in hinzugefügt.');
    }

    public function attachPhysician(): void
    {
        $this->validate(['phys_id' => ['required', $this->tenantExists('physicians')]]);
        $this->resident->physicians()->syncWithoutDetaching([$this->phys_id]);
        $this->reset('phys_id');
        session()->flash('status', 'Arzt/Ärztin zugeordnet.');
    }

    public function createSis(CreateSisAssessment $createSis): void
    {
        // Vorbehaltsaufgabe § 4 PflBG: SIS/Pflegeplanung darf nur eine Pflegefachkraft verantworten.
        abort_unless(app(Befugnis::class)->darfKey(auth()->user(), 'sis_abzeichnen'), 403,
            'Vorbehaltsaufgabe (§ 4 PflBG) — nur Pflegefachkräfte dürfen die SIS verantworten.');

        $felder = [];
        foreach ($this->sis_felder as $key => $text) {
            if (trim((string) $text) !== '') {
                $felder[] = ['themenfeld' => $key, 'freitext' => $text, 'strukturdaten' => null];
            }
        }

        $sis = $createSis->handle(new SisAssessmentData(
            resident_id: $this->resident->id,
            created_by: auth()->id(),
            erstellt_am: now()->format('Y-m-d'),
            eingangsfrage: $this->sis_eingangsfrage ?: null,
            themenfelder: $felder,
        ));

        foreach ($this->sis_risiken as $risiko) {
            $sis->riskItems()->create(['risiko' => $risiko, 'eingeschaetzt' => true]);
        }

        $this->reset('sis_eingangsfrage', 'sis_risiken');
        foreach (SisTopicField::cases() as $f) {
            $this->sis_felder[$f->value] = '';
        }
        session()->flash('status', 'SIS-Erhebung angelegt.');
    }

    public function addMeasure(CreateCareMeasure $createMeasure): void
    {
        $this->validate(['m_themenfeld' => ['required'], 'm_beschreibung' => ['required', 'string']]);
        $createMeasure->handle(new CareMeasureData(
            resident_id: $this->resident->id,
            themenfeld: $this->m_themenfeld,
            beschreibung: $this->m_beschreibung,
            ziel: $this->m_ziel ?: null,
            verantwortlich: auth()->user()->name,
        ));
        $this->reset('m_beschreibung', 'm_ziel', 'm_katalog_search');
        session()->flash('status', 'Maßnahme geplant.');
    }

    public function pickMeasure(int $id): void
    {
        $item = MeasureCatalogItem::find($id);
        if (! $item) {
            return;
        }
        $this->m_beschreibung = $item->bezeichnung;
        $this->m_katalog_search = '';
    }

    /** @return Collection<int, MeasureCatalogItem> */
    private function measureSuggestions(): Collection
    {
        $term = trim($this->m_katalog_search);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return MeasureCatalogItem::query()
            ->whereRaw('LOWER(bezeichnung) LIKE ?', ['%'.mb_strtolower($term).'%'])
            ->orderBy('bezeichnung')
            ->limit(25)
            ->get();
    }

    public function addReport(CreateCareReport $createReport): void
    {
        $this->validate(['r_datum' => ['required', 'date'], 'r_schicht' => ['required', 'in:frueh,spaet,nacht'], 'r_text' => ['required', 'string']]);
        $createReport->handle(new CareReportData(
            resident_id: $this->resident->id,
            created_by: auth()->id(),
            datum: str_replace('T', ' ', $this->r_datum).':00',
            schicht: $this->r_schicht,
            text: $this->r_text,
        ));
        $this->reset('r_text');
        session()->flash('status', 'Bericht gespeichert.');
    }

    public function addEvaluation(CreateEvaluation $createEvaluation): void
    {
        $this->validate(['e_measure' => ['required', $this->tenantExists('care_measures')], 'e_zielerreichung' => ['required', 'in:erreicht,teilweise,nicht']]);
        $createEvaluation->handle(new EvaluationData(
            evaluable_type: CareMeasure::class,
            evaluable_id: $this->e_measure,
            created_by: auth()->id(),
            datum: now()->format('Y-m-d'),
            zielerreichung: $this->e_zielerreichung,
            anlass: $this->e_anlass ?: null,
        ));
        $this->reset('e_anlass');
        session()->flash('status', 'Evaluation erfasst.');
    }

    public function render()
    {
        $this->resident->load([
            'room.station', 'diagnoses.icdCode', 'insurances.healthInsurance',
            'custodians', 'physicians', 'allergies', 'statusObservations', 'devices', 'contacts',
            'sisAssessments' => fn ($q) => $q->current()->latest('id')->with(['topicFields', 'riskItems']),
            'careMeasures' => fn ($q) => $q->current()->latest('id'),
            'careEvents',
        ]);

        return view('livewire.resident-show', [
            'areas' => SisAreaCatalog::all(),
            'topicFields' => SisTopicField::cases(),
            'riskTypes' => RiskType::cases(),
            'diagnosisResults' => $this->diagnosisResults(),
            'measureSuggestions' => $this->measureSuggestions(),
            'indicators' => QualityIndicator::cases(),
            'severities' => EventSeverity::cases(),
            'insurances' => HealthInsurance::orderBy('name')->get(),
            'physicians' => Physician::orderBy('name')->get(),
            'measures' => $this->resident->careMeasures,
            'statusCatalog' => StatusObservationCatalog::all(),
        ]);
    }
}
