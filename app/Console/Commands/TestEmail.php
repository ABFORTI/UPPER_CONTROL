<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use App\Models\User;
use App\Models\Solicitud;
use App\Models\Orden;
use App\Models\Factura;
use App\Notifications\SolicitudCreadaNotification;
use App\Notifications\OtAsignada;
use App\Notifications\OtListaParaCalidad;
use App\Notifications\CalidadResultadoNotification;
use App\Notifications\OtValidadaParaCliente;
use App\Notifications\ClienteAutorizoNotification;
use App\Notifications\OtAutorizadaParaFacturacion;
use App\Notifications\FacturaGeneradaNotification;
use App\Notifications\RecordatorioValidacionOt;
use App\Notifications\SystemEventNotification;

class TestEmail extends Command
{
    protected $signature = 'email:test {tipo?} {email?}';
    protected $description = 'Envía un email de prueba para visualizar el diseño';

    public function handle()
    {
        $tipo = $this->argument('tipo');
        $email = $this->argument('email') ?? 'test@example.com';

        if (!$tipo) {
            $this->info('📧 Tipos de email disponibles:');
            $this->info('');
            $this->line('  1. solicitud     - Nueva Solicitud de Servicio');
            $this->line('  2. ot-asignada   - OT Asignada a Responsable');
            $this->line('  3. ot-calidad    - OT Lista para Calidad');
            $this->line('  4. calidad-ok    - Calidad Aprobada');
            $this->line('  5. calidad-no    - Calidad Rechazada');
            $this->line('  6. ot-validada   - OT Validada para Cliente');
            $this->line('  7. cliente-ok    - Cliente Autorizó OT');
            $this->line('  8. facturacion   - OT Lista para Facturación');
            $this->line('  9. factura       - Factura Generada');
            $this->line(' 10. recordatorio  - Recordatorio de Validación');
            $this->line(' 11. sistema       - Evento del Sistema');
            $this->info('');
            $this->info('Uso: php artisan email:test [tipo] [email]');
            $this->info('Ejemplo: php artisan email:test solicitud tu@email.com');
            return 0;
        }

        // Buscar un usuario real o usar el primero disponible
        $user = User::where('email', $email)->first() ?? User::first();
        
        if (!$user) {
            $this->error('No hay usuarios en la base de datos');
            return 1;
        }

        $this->info("Enviando a: {$user->name} ({$user->email})");
        $this->info('');

        try {
            switch ($tipo) {
                case 'solicitud':
                case '1':
                    $this->enviarSolicitud($user);
                    break;
                case 'ot-asignada':
                case '2':
                    $this->enviarOtAsignada($user);
                    break;
                case 'ot-calidad':
                case '3':
                    $this->enviarOtCalidad($user);
                    break;
                case 'calidad-ok':
                case '4':
                    $this->enviarCalidadOk($user);
                    break;
                case 'calidad-no':
                case '5':
                    $this->enviarCalidadNo($user);
                    break;
                case 'ot-validada':
                case '6':
                    $this->enviarOtValidada($user);
                    break;
                case 'cliente-ok':
                case '7':
                    $this->enviarClienteOk($user);
                    break;
                case 'facturacion':
                case '8':
                    $this->enviarFacturacion($user);
                    break;
                case 'factura':
                case '9':
                    $this->enviarFactura($user);
                    break;
                case 'recordatorio':
                case '10':
                    $this->enviarRecordatorio($user);
                    break;
                case 'sistema':
                case '11':
                    $this->enviarSistema($user);
                    break;
                default:
                    $this->error('Tipo de email no válido');
                    return 1;
            }

            $this->info('✅ Email enviado exitosamente a: ' . $email);
            $this->info('');
            
            if (config('mail.default') === 'log') {
                $this->warn('💡 El email está en modo LOG');
                $this->info('   Ver en: storage/logs/laravel.log');
            } else {
                $this->info('📬 Revisa tu bandeja de entrada');
            }

        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function enviarSolicitud($user)
    {
        $solicitud = $this->crearSolicitudPrueba();
        $user->notify(new SolicitudCreadaNotification($solicitud));
        $this->info('📝 Enviando: Nueva Solicitud de Servicio');
    }

    private function enviarOtAsignada($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new OtAsignada($orden));
        $this->info('🎯 Enviando: OT Asignada a Responsable');
    }

    private function enviarOtCalidad($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new OtListaParaCalidad($orden));
        $this->info('✅ Enviando: OT Lista para Calidad');
    }

    private function enviarCalidadOk($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new CalidadResultadoNotification($orden, 'Aprobado', 'Trabajo excelente, todo en orden.'));
        $this->info('✅ Enviando: Calidad Aprobada');
    }

    private function enviarCalidadNo($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new CalidadResultadoNotification($orden, 'Rechazado', 'Se encontraron deficiencias en el acabado. Favor de corregir.'));
        $this->info('❌ Enviando: Calidad Rechazada');
    }

    private function enviarOtValidada($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new OtValidadaParaCliente($orden));
        $this->info('✅ Enviando: OT Validada para Cliente');
    }

    private function enviarClienteOk($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new ClienteAutorizoNotification($orden));
        $this->info('✅ Enviando: Cliente Autorizó OT');
    }

    private function enviarFacturacion($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new OtAutorizadaParaFacturacion($orden));
        $this->info('💰 Enviando: OT Lista para Facturación');
    }

    private function enviarFactura($user)
    {
        $orden = $this->crearOrdenPrueba();
        $factura = $this->crearFacturaPrueba($orden);
        $user->notify(new FacturaGeneradaNotification($factura));
        $this->info('📄 Enviando: Factura Generada');
    }

    private function enviarRecordatorio($user)
    {
        $orden = $this->crearOrdenPrueba();
        $user->notify(new RecordatorioValidacionOt($orden, 48)); // 48 horas = 2 días
        $this->info('⏰ Enviando: Recordatorio de Validación');
    }

    private function enviarSistema($user)
    {
        $user->notify(new SystemEventNotification(
            'Actualización del Sistema',
            'El sistema se actualizará el próximo sábado a las 2:00 AM. El tiempo estimado de inactividad es de 30 minutos.',
            route('dashboard')
        ));
        $this->info('🔔 Enviando: Evento del Sistema');
    }

    private function crearSolicitudPrueba()
    {
        $solicitud = new Solicitud();
        $solicitud->id = 999;
        $solicitud->folio = 'SOL-2025-999';
        $solicitud->tamano = 'Grande';
        $solicitud->created_at = now();
        
        // Crear relaciones mock
        $solicitud->setRelation('servicio', (object)[
            'id' => 1,
            'nombre' => 'Mantenimiento Preventivo'
        ]);
        
        $solicitud->setRelation('centro', (object)[
            'id' => 1,
            'nombre' => 'Planta Norte - Zona Industrial'
        ]);
        
        return $solicitud;
    }

    private function crearOrdenPrueba()
    {
        $orden = new Orden();
        $orden->id = 42;
        $orden->created_at = now();
        
        $orden->setRelation('servicio', (object)[
            'id' => 1,
            'nombre' => 'Mantenimiento Preventivo'
        ]);
        
        $orden->setRelation('centro', (object)[
            'id' => 1,
            'nombre' => 'Planta Norte - Zona Industrial'
        ]);
        
        $orden->setRelation('responsable', (object)[
            'id' => 1,
            'name' => 'Juan Pérez López'
        ]);
        
        return $orden;
    }

    private function crearFacturaPrueba($orden)
    {
        $factura = new Factura();
        $factura->id = 123;
        $factura->total = 15750.50;
        $factura->pdf_path = null; // Simular que no hay PDF todavía
        $factura->created_at = now();
        $factura->setRelation('orden', $orden);
        
        return $factura;
    }
}
