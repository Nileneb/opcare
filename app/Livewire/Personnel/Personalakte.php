<?php

namespace App\Livewire\Personnel;

use App\Domains\Identity\Actions\AssignRole;
use App\Domains\Identity\Models\User;
use App\Domains\Personnel\Enums\Beschaeftigungsart;
use App\Domains\Personnel\Enums\Familienstand;
use App\Domains\Personnel\Enums\Geschlecht;
use App\Domains\Personnel\Enums\Krankenversicherung;
use App\Domains\Personnel\Enums\Masernschutz;
use App\Domains\Personnel\Enums\Qualifikation;
use App\Domains\Personnel\Enums\Steuerklasse;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Spatie\Permission\Models\Role;

/**
 * Vollständige Personalakte (Personalfragebogen) eines App-Benutzers — sektioniert + datengetrieben aus
 * FIELDS. Koppelt die Rollen-Zuweisung direkt an: alles, was Mitarbeitende betrifft, an einer Stelle.
 * Sensible Felder (Steuer-ID/SV-Nr/IBAN) sind im Model verschlüsselt.
 */
#[Layout('layouts.app')]
class Personalakte extends Component
{
    public User $employee;

    /** @var array<string, mixed> */
    public array $f = [];

    public string $role = '';

    /** Sektion → [feld => [Label, Typ]] · Typ: text|date|bool|number oder ein Enum-Klassenname. */
    private const FIELDS = [
        'Persönliche Angaben' => [
            'personalnummer' => ['Personalnummer', 'text'], 'anrede' => ['Anrede', 'text'],
            'vorname' => ['Vorname', 'text'], 'nachname' => ['Nachname', 'text'], 'geburtsname' => ['Geburtsname', 'text'],
            'geburtsdatum' => ['Geburtsdatum', 'date'], 'geburtsort' => ['Geburtsort', 'text'],
            'staatsangehoerigkeit' => ['Staatsangehörigkeit', 'text'],
            'geschlecht' => ['Geschlecht', Geschlecht::class], 'familienstand' => ['Familienstand', Familienstand::class],
            'strasse' => ['Straße', 'text'], 'hausnummer' => ['Hausnr.', 'text'], 'plz' => ['PLZ', 'text'], 'ort' => ['Ort', 'text'],
            'telefon' => ['Telefon', 'text'],
            'schwerbehinderung' => ['Schwerbehinderung', 'bool'], 'grad_behinderung' => ['Grad d. Behinderung', 'number'],
        ],
        'Steuer (ELStAM)' => [
            'steuer_id' => ['Steuer-ID (IdNr)', 'text'], 'steuerklasse' => ['Steuerklasse', Steuerklasse::class],
            'konfession' => ['Konfession (Kirchensteuer)', 'text'], 'kinderfreibetraege' => ['Kinderfreibeträge', 'number'],
        ],
        'Sozialversicherung' => [
            'sv_nummer' => ['Sozialversicherungsnummer', 'text'], 'krankenkasse' => ['Krankenkasse', 'text'],
            'krankenversicherung' => ['Versicherungsart', Krankenversicherung::class],
        ],
        'Bankverbindung' => [
            'iban' => ['IBAN', 'text'], 'bic' => ['BIC', 'text'], 'kontoinhaber' => ['Kontoinhaber (falls abweichend)', 'text'],
        ],
        'Beschäftigung & Vertrag' => [
            'eintritt_am' => ['Eintritt', 'date'], 'austritt_am' => ['Austritt', 'date'],
            'befristet_bis' => ['befristet bis', 'date'], 'probezeit_bis' => ['Probezeit bis', 'date'],
            'beschaeftigungsart' => ['Beschäftigungsart', Beschaeftigungsart::class],
            'wochenstunden' => ['Wochenstunden (Pensum)', 'number'], 'position' => ['Position', 'text'],
            'urlaubsanspruch' => ['Urlaubsanspruch (Tage/Jahr)', 'number'],
        ],
        'Qualifikation & Pflege-Compliance' => [
            'qualifikation' => ['Qualifikation', Qualifikation::class], 'berufsurkunde_nr' => ['Berufsurkunde-Nr.', 'text'],
            'fuehrungszeugnis_am' => ['erw. Führungszeugnis vom', 'date'], 'masernschutz' => ['Masernschutz (§ 20 IfSG)', Masernschutz::class],
            'notfallkontakt_name' => ['Notfallkontakt', 'text'], 'notfallkontakt_telefon' => ['Notfallkontakt Telefon', 'text'],
        ],
    ];

    public function mount(User $user): void
    {
        $this->authorize('update', $user);
        $this->employee = $user;
        $this->role = $user->roles->pluck('name')->first() ?? '';

        $profile = $user->employeeProfile;
        foreach (self::FIELDS as $felder) {
            foreach ($felder as $key => [$label, $type]) {
                $val = $profile?->{$key};
                if ($val instanceof \BackedEnum) {
                    $val = $val->value;
                } elseif ($val instanceof Carbon) {
                    $val = $val->toDateString();
                }
                $this->f[$key] = $type === 'bool' ? (bool) $val : ($val ?? '');
            }
        }
    }

    public function speichern(): void
    {
        $this->authorize('update', $this->employee);
        $this->validate([
            'f.wochenstunden' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'f.grad_behinderung' => ['nullable', 'integer', 'min:0', 'max:100'],
            'f.steuer_id' => ['nullable', 'string', 'max:20'],
            'f.iban' => ['nullable', 'string', 'max:34'],
            'f.plz' => ['nullable', 'string', 'max:10'],
        ]);

        $attrs = [];
        foreach ($this->f as $key => $val) {
            $attrs[$key] = $val === '' ? null : $val;
        }
        $this->employee->employeeProfile()->updateOrCreate(['user_id' => $this->employee->id], $attrs);
        session()->flash('status', 'Personalakte gespeichert.');
    }

    public function setRole(AssignRole $assign): void
    {
        $this->authorize('update', $this->employee);
        abort_unless(in_array($this->role, Role::pluck('name')->all(), true), 422);
        // WHY(privilege-escalation): admin darf keine super-admin-Rolle vergeben
        abort_if($this->role === 'super-admin' && ! auth()->user()->isSuperAdmin(), 403);

        $assign->handle($this->employee, $this->role);
        session()->flash('status', 'Rolle aktualisiert.');
    }

    public function render()
    {
        return view('livewire.personnel.personalakte', [
            'sections' => self::FIELDS,
            'roles' => Role::orderBy('name')->pluck('name'),
        ]);
    }
}
