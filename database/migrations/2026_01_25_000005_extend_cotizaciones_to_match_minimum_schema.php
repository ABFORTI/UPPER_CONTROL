<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('cotizaciones', 'currency')) {
                $table->string('currency', 3)->default('MXN')->after('estatus');
            }

            if (!Schema::hasColumn('cotizaciones', 'approval_token_hash')) {
                // sha256 hex = 64
                $table->string('approval_token_hash', 64)->nullable()->after('rejected_at');
            }

            // Alinear nombre de "tax" con sistema actual (iva ya existe)
            if (!Schema::hasColumn('cotizaciones', 'tax')) {
                $table->decimal('tax', 12, 2)->nullable()->after('subtotal');
            }

            if (!Schema::hasColumn('cotizaciones', 'notes')) {
                $table->text('notes')->nullable()->after('tax');
            }
        });

        Schema::table('cotizaciones', function (Blueprint $table) {
            // Índices
            if (Schema::hasColumn('cotizaciones', 'approval_token_hash')) {
                try { $table->index(['approval_token_hash']); } catch (Throwable $e) {}
            }
            if (Schema::hasColumn('cotizaciones', 'expires_at')) {
                try { $table->index(['expires_at']); } catch (Throwable $e) {}
            }
            if (Schema::hasColumn('cotizaciones', 'sent_at')) {
                try { $table->index(['sent_at']); } catch (Throwable $e) {}
            }
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones', function (Blueprint $table) {
            try { $table->dropIndex(['approval_token_hash']); } catch (Throwable $e) {}
            try { $table->dropIndex(['expires_at']); } catch (Throwable $e) {}
            try { $table->dropIndex(['sent_at']); } catch (Throwable $e) {}

            if (Schema::hasColumn('cotizaciones', 'currency')) {
                $table->dropColumn('currency');
            }
            if (Schema::hasColumn('cotizaciones', 'approval_token_hash')) {
                $table->dropColumn('approval_token_hash');
            }
            if (Schema::hasColumn('cotizaciones', 'tax')) {
                $table->dropColumn('tax');
            }
            if (Schema::hasColumn('cotizaciones', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
