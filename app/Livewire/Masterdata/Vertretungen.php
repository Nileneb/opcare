<?php

namespace App\Livewire\Masterdata;

use App\Domains\Identity\Actions\CreateUser;
use App\Domains\Identity\Data\AdminUserData;
use App\Domains\Identity\Models\User;
use App\Domains\Masterdata\Enums\Aufgabenkreis;
use App\Domains\Masterdata\Enums\EreignisKategorie;
use App\Domains\Masterdata\Enums\VertretungTyp;
use App\Domains\Masterdata\Models\BewohnerEreignis;
use App\Domains\Masterdata\Models\Custodian;
use App\Domains\Masterdata\Models\Resident;
use App\Domains\Masterdata\Notifications\BewohnerEreignisGemeldet;
use App\Support\Concerns\ScopesTenantValidation;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Leitungs-Sicht auf die rechtlichen Vertretungen (§§ 1814 ff. BGB): Aufgabenkreise, Pflicht-mit-Frist
 * (§ 1863 Bericht), Login-Konten und der Ereignis-Workflow (§ 1821 Beteiligungs-/Informationsrecht).
 */
#[Layout('layouts.app')]
class Vertretungen extends Component
{
    use ScopesTenantValidation;

    public ?int $v_resident_id = null;

    public string $v_typ = 'gesetzlicher_betreuer';

    /** @var array<int, string> */
    public array $v_kreise = [];

    public string $v_name = '';

    public string $v_email = '';

    public string $v_kontakt = '';

    public bool $v_beruflich = false;

    public string $v_gericht = '';

    public string $v_aktenzeichen = '';

    public string $v_gueltig_bis = '';

    public ?int $v_bericht_intervall = null;

    public ?int $e_resident_id = null;

    public string $e_kategorie = 'md_begutachtung';

    public string $e_titel = '';

    public string $e_datum = '';

    public string $e_beschreibung = '';

    public function darfVerwalten(): bool
    {
        $u = auth()->user();

        return $u !== null && ($u->isSuperAdmin() || $u->hasAnyRole(['admin', 'pflegefachkraft']));
    }

    public function vertretungAnlegen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate([
            'v_resident_id' => ['required', $this->tenantExists('residents')],
            'v_typ' => ['required', 'in:'.implode(',', array_column(VertretungTyp::cases(), 'value'))],
            'v_kreise' => ['array'],
            'v_kreise.*' => ['in:'.implode(',', array_column(Aufgabenkreis::cases(), 'value'))],
            'v_name' => ['required', 'string', 'max:255'],
            'v_email' => ['nullable', 'email', 'max:255'],
            'v_kontakt' => ['nullable', 'string', 'max:255'],
            'v_gericht' => ['nullable', 'string', 'max:255'],
            'v_aktenzeichen' => ['nullable', 'string', 'max:255'],
            'v_gueltig_bis' => ['nullable', 'date'],
            'v_bericht_intervall' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        Custodian::create([
            'resident_id' => $this->v_resident_id,
            'typ' => $this->v_typ,
            'aufgabenkreise' => array_values($this->v_kreise),
            'name' => $this->v_name,
            'kontakt' => $this->v_kontakt ?: null,
            'email' => $this->v_email ?: null,
            'beruflich' => $this->v_beruflich,
            'gericht' => $this->v_gericht ?: null,
            'aktenzeichen' => $this->v_aktenzeichen ?: null,
            'gueltig_bis' => $this->v_gueltig_bis ?: null,
            'bericht_intervall_monate' => $this->v_bericht_intervall,
        ]);

        $this->reset('v_resident_id', 'v_kreise', 'v_name', 'v_email', 'v_kontakt', 'v_beruflich',
            'v_gericht', 'v_aktenzeichen', 'v_gueltig_bis', 'v_bericht_intervall');
        $this->v_typ = 'gesetzlicher_betreuer';
        session()->flash('status', 'Vertretung angelegt.');
    }

    public function vertretungLoeschen(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        Custodian::whereKey($id)->delete();
        session()->flash('status', 'Vertretung entfernt.');
    }

    public function berichtErledigt(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $v = Custodian::findOrFail($id);
        $v->update(['letzter_bericht_am' => now()->toDateString()]);
        session()->flash('status', 'Bericht als erledigt vermerkt.');
    }

    public function kontoAnlegen(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $v = Custodian::findOrFail($id);
        if ($v->user_id !== null) {
            return;
        }
        if ($v->email === null) {
            $this->addError('konto', 'Für ein Konto wird eine E-Mail-Adresse der Vertretung benötigt.');

            return;
        }

        $rolle = $v->typ === VertretungTyp::Angehoeriger ? 'angehoeriger' : 'betreuer';
        $existing = User::where('email', $v->email)->first();
        if ($existing !== null) {
            $v->update(['user_id' => $existing->id]);
            session()->flash('status', "Bestehendes Konto {$v->email} verknüpft.");

            return;
        }

        $passwort = Str::password(12);
        $user = app(CreateUser::class)->handle(new AdminUserData(
            name: $v->name, email: $v->email, password: $passwort, role: $rolle,
        ));
        $v->update(['user_id' => $user->id]);
        // WHY: kein Mailversand in Dev — Initialpasswort einmalig anzeigen, damit die Leitung es übergeben kann.
        session()->flash('status', "Konto angelegt: {$v->email} — Initialpasswort: {$passwort}");
    }

    public function ereignisErfassen(): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $this->validate([
            'e_resident_id' => ['required', $this->tenantExists('residents')],
            'e_kategorie' => ['required', 'in:'.implode(',', array_column(EreignisKategorie::cases(), 'value'))],
            'e_titel' => ['required', 'string', 'max:255'],
            'e_datum' => ['required', 'date'],
            'e_beschreibung' => ['nullable', 'string'],
        ]);

        $ereignis = BewohnerEreignis::create([
            'resident_id' => $this->e_resident_id,
            'kategorie' => $this->e_kategorie,
            'titel' => $this->e_titel,
            'datum' => $this->e_datum,
            'beschreibung' => $this->e_beschreibung ?: null,
            'status' => 'offen',
            'erstellt_von_user_id' => auth()->id(),
        ]);

        $ereignis->load('resident.custodians');
        $benachrichtigt = $this->benachrichtige($ereignis);
        $berechtigt = $ereignis->empfaenger()->count();

        $this->reset('e_resident_id', 'e_titel', 'e_datum', 'e_beschreibung');
        $this->e_kategorie = 'md_begutachtung';
        session()->flash('status', "Ereignis erfasst. {$berechtigt} berechtigte Vertretung(en), davon {$benachrichtigt} mit Konto benachrichtigt.");
    }

    public function ereignisInformiert(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        $e = BewohnerEreignis::findOrFail($id);
        $e->update(['status' => 'informiert', 'informiert_am' => now()->toDateString()]);
        session()->flash('status', 'Als „informiert“ dokumentiert.');
    }

    public function ereignisErledigt(int $id): void
    {
        abort_unless($this->darfVerwalten(), 403);
        BewohnerEreignis::whereKey($id)->update(['status' => 'erledigt']);
        session()->flash('status', 'Ereignis abgeschlossen.');
    }

    private function benachrichtige(BewohnerEreignis $ereignis): int
    {
        $userIds = $ereignis->empfaenger()
            ->filter(fn (Custodian $c): bool => $c->user_id !== null)
            ->pluck('user_id')
            ->all();

        if ($userIds === []) {
            return 0;
        }

        $users = User::whereIn('id', $userIds)->get();
        Notification::send($users, new BewohnerEreignisGemeldet(
            $ereignis->id,
            $ereignis->resident->name,
            $ereignis->kategorie->label(),
            $ereignis->titel,
        ));

        return $users->count();
    }

    public function render()
    {
        return view('livewire.masterdata.vertretungen', [
            'residents' => Resident::orderBy('name')->get(),
            'vertretungen' => Custodian::with(['resident', 'user'])
                ->orderBy('resident_id')->orderBy('name')->get(),
            'ereignisse' => BewohnerEreignis::with('resident')
                ->orderByDesc('datum')->limit(50)->get(),
            'kreise' => Aufgabenkreis::cases(),
            'typen' => VertretungTyp::cases(),
            'kategorien' => EreignisKategorie::cases(),
        ]);
    }
}
