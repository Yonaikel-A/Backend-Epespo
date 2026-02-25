<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('codigo_anterior')->nullable()->unique();
            $table->string('nombre');
            $table->string('descripcion');
            $table->string('categoria');
            $table->string('marca')->nullable();
            $table->string('modelo')->nullable();
            $table->string('numero_serie')->nullable();
            $table->string('dimensiones')->nullable();
            $table->string('color')->nullable();
            $table->unique(
                ['categoria', 'numero_serie'],
                'productos_categoria_numero_serie_unique'
            );
            $table->boolean('es_donado')->default(false);
            $table->date('fecha_ingreso')->nullable();
            $table->unsignedBigInteger('ubicacion_id')->nullable();
            $table->foreign('ubicacion_id')
                  ->references('id')
                  ->on('departamentos')
                  ->onDelete('set null');
            $table->string('estado')->default('Activo');
            $table->text('motivo_baja')->nullable();
            $table->timestamp('fecha_baja')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
