<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ot_cortes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ot_id')
                  ->constrained('ordenes_trabajo')
                  ->cascadeOnDelete();
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->string('folio_corte', 50)->unique();
            $table->string('estatus', 20)->default('draft')
                  ->comment('draft, ready_to_bill, billed, void');
            $table->decimal('monto_total', 14, 2)->default(0);
            $table->foreignId('created_by')
                  ->constrained('users')
                  ->restrictOnDelete();
            $table->foreignId('ot_hija_id')
                  ->nullable()
                  ->constrained('ordenes_trabajo')
                  ->nullOnDelete();
            $table->timestamps();

            $table->index(['ot_id', 'periodo_fin']);
            $table->index(['estatus']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ot_cortes');
    }
};
