<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->unique();        
            $table->unsignedBigInteger('responsable_id')->nullable();
            $table->unsignedBigInteger('asignacion_id')->nullable(); 
            $table->date('fecha_creacion');
            $table->string('estado')->default('Generada');
            $table->string('archivo_path')->nullable();
            $table->timestamps();
            $table->foreign('responsable_id')
                  ->references('id')
                  ->on('responsables')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actas');
    }
};
