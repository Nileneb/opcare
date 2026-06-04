<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// WHY(plan4-mehrmandanten, migration): additive Migration für bestehende Deployments
// die permission-Tabellen ohne teams=true angelegt haben. Bei migrate:fresh
// übernimmt die spatie-Migration die Spalten bereits — dieser Guard verhindert Duplikate.
//
// Frische Installationen: spatie-Migration (teams=true) legt tenant_id incl. Composite-PK
// bereits an. Diese Migration ist ein idempotenter Fallback für Bestands-DBs. Ein
// PK-Umbau für Legacy-DBs ist hier bewusst NICHT enthalten — kein Prod-Bestand vorhanden.
return new class extends Migration
{
    public function up(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $t) {
            if (Schema::hasColumn($t, 'tenant_id')) {
                continue;
            }
            Schema::table($t, function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $t) {
            if (Schema::hasColumn($t, 'tenant_id')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('tenant_id'));
            }
        }
        if (Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', fn (Blueprint $table) => $table->dropColumn('tenant_id'));
        }
    }
};
