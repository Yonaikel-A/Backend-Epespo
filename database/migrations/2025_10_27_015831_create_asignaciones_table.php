<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asignaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('responsable_id')->constrained('responsables')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('departamentos')->cascadeOnDelete();

            $table->date('fecha_asignacion');
            $table->string('categoria');

            // acta opcional
            $table->foreignId('acta_id')->nullable()->constrained('actas')->nullOnDelete();

            $table->timestamps();

            $table->index(['responsable_id', 'area_id']);
        });

        Schema::create('asignacion_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asignacion_id')->constrained('asignaciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();

            $table->timestamps();

            // ✅ evita duplicados
            $table->unique(['asignacion_id', 'producto_id'], 'asig_prod_unique');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asignacion_producto');
        Schema::dropIfExists('asignaciones');
    }
};
