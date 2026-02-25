<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->string('accion');
            $table->text('descripcion');
            $table->foreignId('usuario_id')
                ->constrained('usuarios')
                ->nullOnDelete();

            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->foreignId('asignacion_id')->nullable()->constrained('asignaciones')->nullOnDelete();
            $table->foreignId('acta_id')->nullable()->constrained('actas')->nullOnDelete();

            $table->timestamp('fecha')->useCurrent();

            $table->index('usuario_id');
            $table->index('producto_id');
            $table->index('asignacion_id');
            $table->index('acta_id');
            $table->index('fecha');
        });
    }

    public function down(): void {
        Schema::dropIfExists('movimientos');
    }
};
