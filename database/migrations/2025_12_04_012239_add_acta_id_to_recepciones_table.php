<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            if (!Schema::hasColumn('recepciones', 'acta_id')) {
                $table->unsignedBigInteger('acta_id')->nullable()->after('categoria');

                $table->foreign('acta_id')
                    ->references('id')
                    ->on('actas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepciones', function (Blueprint $table) {
            if (Schema::hasColumn('recepciones', 'acta_id')) {
                $table->dropForeign(['acta_id']);
                $table->dropColumn('acta_id');
            }
        });
    }
};
