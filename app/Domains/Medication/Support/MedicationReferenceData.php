<?php

namespace App\Domains\Medication\Support;

/**
 * Kuratierte Medikations-Stammdaten (Darreichungsformen + Bedarf-Anlässe),
 * abgeleitet aus den OPDE-Inhaltsdaten (dosageform / situations). Tenant-neutrale
 * Vorlage — der MedicationReferenceSeeder legt sie pro Mandant an.
 */
final class MedicationReferenceData
{
    /** @return array<int, array{name:string, einheit:string, teilbar:bool}> */
    public static function tradeForms(): array
    {
        return [
            ['name' => 'Tablette', 'einheit' => 'Stück', 'teilbar' => true],
            ['name' => 'Filmtablette', 'einheit' => 'Stück', 'teilbar' => true],
            ['name' => 'Retardtablette', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Kapsel', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Brausetablette', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Dragee', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Tropfen', 'einheit' => 'Tropfen', 'teilbar' => false],
            ['name' => 'Lösung', 'einheit' => 'ml', 'teilbar' => false],
            ['name' => 'Saft/Sirup', 'einheit' => 'ml', 'teilbar' => false],
            ['name' => 'Zäpfchen', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Injektion s.c.', 'einheit' => 'ml', 'teilbar' => false],
            ['name' => 'Injektion i.m.', 'einheit' => 'ml', 'teilbar' => false],
            ['name' => 'Injektion i.v.', 'einheit' => 'ml', 'teilbar' => false],
            ['name' => 'Augentropfen', 'einheit' => 'Tropfen', 'teilbar' => false],
            ['name' => 'Salbe', 'einheit' => 'Anwendung', 'teilbar' => false],
            ['name' => 'Creme', 'einheit' => 'Anwendung', 'teilbar' => false],
            ['name' => 'Pflaster (transdermal)', 'einheit' => 'Stück', 'teilbar' => false],
            ['name' => 'Inhalation', 'einheit' => 'Hub', 'teilbar' => false],
        ];
    }

    /** @return array<int, string> Bedarfsmedikations-Anlässe */
    public static function situations(): array
    {
        return [
            'Schmerzen',
            'nächtliche Unruhe',
            'zunehmende, starke Unruhe',
            'Übelkeit',
            'Obstipation (ab 3. Tag)',
            'Schlafstörungen',
            'Fieber ab 38,5 °C',
            'niedriger Blutdruck',
            'Blutdruck über 150/90',
            'Atemnot',
            'Angst/Agitation',
            'Hautrötung/-schäden',
        ];
    }
}
