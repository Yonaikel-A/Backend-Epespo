<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('acta_id');
            $table->text('motivo_anulacion')->nullable()->after('activo');
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->boolean('activo')->default(true)->after('acta_id');
            $table->text('motivo_anulacion')->nullable()->after('activo');
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones', function (Blueprint $table) {
            $table->dropColumn(['activo', 'motivo_anulacion']);
        });

        Schema::table('recepciones', function (Blueprint $table) {
            $table->dropColumn(['activo', 'motivo_anulacion']);
        });
    }
};
