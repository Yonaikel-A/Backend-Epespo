<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recepciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('responsable_id')->constrained('responsables')->cascadeOnDelete();
            $table->foreignId('area_id')->constrained('departamentos')->cascadeOnDelete();

            $table->date('fecha_devolucion');
            $table->string('categoria')->nullable();

            // ✅ acta opcional
            $table->foreignId('acta_id')->nullable()->constrained('actas')->nullOnDelete();

            $table->timestamps();

            $table->index(['responsable_id', 'area_id']);
        });

        Schema::create('recepcion_producto', function (Blueprint $table) {
            $table->id();

            $table->foreignId('recepcion_id')->constrained('recepciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();

            $table->timestamps();

            // ✅ evita duplicados
            $table->unique(['recepcion_id', 'producto_id'], 'rec_prod_unique');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_producto');
        Schema::dropIfExists('recepciones');
    }
};
