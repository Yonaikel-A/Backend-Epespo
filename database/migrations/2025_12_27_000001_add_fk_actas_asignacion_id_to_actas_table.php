<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->foreign('asignacion_id')
                ->references('id')
                ->on('asignaciones')
                ->nullOnDelete();

            $table->index('asignacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('actas', function (Blueprint $table) {
            $table->dropForeign(['asignacion_id']);
            $table->dropIndex(['asignacion_id']);
        });
    }
};
