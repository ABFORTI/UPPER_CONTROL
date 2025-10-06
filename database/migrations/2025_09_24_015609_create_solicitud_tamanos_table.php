<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitud_tamanos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_solicitud')->constrained('solicitudes')->cascadeOnDelete();
            // chico | mediano | grande   (si tu app usa otras etiquetas, cámbialas aquí)
            $t->enum('tamano', ['chico','mediano','grande']);
            $t->unsignedInteger('cantidad');
            $t->timestamps();

            $t->unique(['id_solicitud', 'tamano']); // evita duplicados por tamaño en la misma solicitud
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitud_tamanos');
    }
};
