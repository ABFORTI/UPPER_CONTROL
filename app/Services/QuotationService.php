<?php

namespace App\Services;

use App\Models\Cotizacion;
use App\Models\CotizacionItemServicio;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuotationService
{
    /**
     * Recalcula subtotal/tax/total de la cotización basándose en sus servicios.
     *
     * - Recalcula también subtotal/iva/total de cada línea (CotizacionItemServicio) usando precio_unitario * qty.
     * - Mantiene compatibilidad: actualiza tanto `tax` como `iva` con el mismo importe.
     */
    public function recalculateTotals(Cotizacion $cotizacion, float $taxRate = 0.16): Cotizacion
    {
        $cotizacion->loadMissing(['items.servicios']);

        return DB::transaction(function () use ($cotizacion, $taxRate) {
            /** @var Cotizacion $locked */
            $locked = Cotizacion::whereKey($cotizacion->id)->lockForUpdate()->firstOrFail();
            $locked->loadMissing(['items.servicios']);

            $subtotalAll = 0.0;
            $taxAll = 0.0;
            $totalAll = 0.0;

            foreach ($locked->items as $item) {
                foreach ($item->servicios as $line) {
                    /** @var CotizacionItemServicio $line */
                    $qty = $line->qty !== null ? (float)$line->qty : (float)($line->cantidad ?? 1);
                    if ($qty <= 0) $qty = 1;

                    $pu = (float)($line->precio_unitario ?? 0);
                    $sub = $pu * $qty;
                    $tax = $sub * $taxRate;
                    $tot = $sub + $tax;

                    $line->fill([
                        'subtotal' => $sub,
                        'iva' => $tax,
                        'total' => $tot,
                    ])->save();

                    $subtotalAll += $sub;
                    $taxAll += $tax;
                    $totalAll += $tot;
                }
            }

            $locked->fill([
                'subtotal' => $subtotalAll,
                'tax' => $taxAll,
                'iva' => $taxAll,
                'total' => $totalAll,
            ])->save();

            return $locked;
        });
    }

    /**
     * Valida que una cotización se pueda editar.
     * Solo permite edición cuando está en draft.
     */
    public function assertEditable(Cotizacion $cotizacion): void
    {
        if ($cotizacion->estatus !== Cotizacion::ESTATUS_DRAFT) {
            throw new DomainException('La cotización no se puede editar en el estatus actual.');
        }
    }

    public function canEdit(Cotizacion $cotizacion): bool
    {
        return $cotizacion->estatus === Cotizacion::ESTATUS_DRAFT;
    }

    /**
     * Genera un folio único con formato: PREFIX-YYYY-####
     * Ej: COT-2026-0001
     */
    public function generateFolio(string $prefix = 'COT', ?int $year = null, int $pad = 4): string
    {
        $year = $year ?: (int)now()->format('Y');
        $prefix = strtoupper(trim($prefix));

        $base = $prefix . '-' . $year . '-';

        return DB::transaction(function () use ($base, $pad) {
            $lastFolio = Cotizacion::where('folio', 'like', $base . '%')
                ->orderByDesc('folio')
                ->lockForUpdate()
                ->value('folio');

            $seq = 1;
            if (is_string($lastFolio) && preg_match('/-(\d+)$/', $lastFolio, $m)) {
                $seq = ((int)$m[1]) + 1;
            }

            return sprintf('%s%0' . $pad . 'd', $base, $seq);
        });
    }

    /**
     * Genera token seguro para aprobación.
     * - Genera token aleatorio (URL-safe)
     * - Guarda SOLO el hash SHA-256 en cotizaciones.approval_token_hash
     * - Retorna el token plano para construir el link en email
     */
    public function generateApprovalToken(Cotizacion $cotizacion): string
    {
        $token = $this->randomUrlToken(32);
        $hash = hash('sha256', $token);

        $cotizacion->forceFill([
            'approval_token_hash' => $hash,
        ])->save();

        return $token;
    }

    public function approvalTokenMatches(Cotizacion $cotizacion, string $plainToken): bool
    {
        if (!$cotizacion->approval_token_hash) return false;
        return hash_equals((string)$cotizacion->approval_token_hash, hash('sha256', $plainToken));
    }

    private function randomUrlToken(int $bytes = 32): string
    {
        // base64url sin padding: apto para URL/email
        $raw = random_bytes($bytes);
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
