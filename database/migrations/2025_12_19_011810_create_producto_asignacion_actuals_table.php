<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producto_asignaciones_actuales', function (Blueprint $table) {
            $table->id();

            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('responsable_id')->constrained('responsables')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('departamentos')->cascadeOnDelete();
            $table->foreignId('asignacion_id')->constrained('asignaciones')->cascadeOnDelete();

            $table->date('fecha_asignacion');

            $table->timestamps();

            $table->unique('producto_id');
            $table->index(['responsable_id', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_asignaciones_actuales');
    }
};
