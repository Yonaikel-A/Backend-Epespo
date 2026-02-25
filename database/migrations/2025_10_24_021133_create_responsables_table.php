<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('responsables', function (Blueprint $table) {
            $table->id();

            $table->string('titulo')->nullable();
            $table->string('nombre');
            $table->string('apellido');
            $table->string('correo')->unique();
            $table->string('cedula', 10)->unique();
            $table->string('cargo');
            $table->boolean('activo')->default(true);
            $table->date('fecha_inactivacion')->nullable();
            $table->string('motivo_inactivacion', 190)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('responsables');
    }
};
