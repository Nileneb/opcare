<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

/**
 * Track B (At-Rest): verschlüsselt bestehende Klartext-Werte der sensiblen Gesundheits-Freitext-Spalten
 * nachträglich, damit Modelle mit `encrypted`-Cast Bestandsdaten lesen können (sonst DecryptException).
 * Idempotent: bereits verschlüsselte Werte (try-decrypt erfolgreich) werden übersprungen.
 */
return new class extends Migration
{
    /** @var array<string, array<int, string>> */
    private array $targets = [
        'care_reports' => ['text'],
        'risk_items' => ['begruendung'],
        'care_measures' => ['beschreibung', 'ziel'],
        'transcription_jobs' => ['rohtranskript', 'sis_vorschlag'],
        'assessments' => ['notiz'],
        'sis_topic_field_entries' => ['freitext', 'strukturdaten'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $columns) {
                $update = [];
                foreach ($columns as $col) {
                    $value = $row->{$col} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    try {
                        Crypt::decryptString($value); // bereits verschlüsselt → nichts tun
                    } catch (DecryptException) {
                        $update[$col] = Crypt::encryptString($value);
                    }
                }
                if ($update !== []) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            DB::table($table)->orderBy('id')->each(function ($row) use ($table, $columns) {
                $update = [];
                foreach ($columns as $col) {
                    $value = $row->{$col} ?? null;
                    if ($value === null || $value === '') {
                        continue;
                    }
                    try {
                        $update[$col] = Crypt::decryptString($value);
                    } catch (DecryptException) {
                        // bereits Klartext → nichts tun
                    }
                }
                if ($update !== []) {
                    DB::table($table)->where('id', $row->id)->update($update);
                }
            });
        }
    }
};
