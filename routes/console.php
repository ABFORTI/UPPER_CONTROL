<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorios de validación de OT para clientes
Schedule::command('recordatorios:validacion-ot')->everyMinute();

// Test scheduler
Schedule::command('test:schedule')->everyMinute();

// Comando utilitario: enviar un correo RAW de prueba sin depender de BD ni notificaciones
// Uso: php artisan mail:raw destinatario@dominio.com --subject="Asunto" --text="Cuerpo del mensaje"
Artisan::command('mail:raw {to} {--subject=Test desde Laravel} {--text=Correo de prueba} {--from=}', function () {
    $to = $this->argument('to');
    $subject = (string) $this->option('subject');
    $text = (string) $this->option('text');
    $from = (string) ($this->option('from') ?? '');

    $this->info("Enviando correo de prueba a: {$to}");

    try {
        Mail::raw($text, function ($m) use ($to, $subject, $from) {
            if (!empty($from)) {
                $m->from($from, config('mail.from.name'));
            }
            $m->to($to)->subject($subject);
        });
        $this->info('✅ Envío solicitado al transport configurado (verifica tu bandeja).');

        if (config('mail.default') === 'log') {
            $this->warn('⚠️  Actualmente MAIL_MAILER=log — revisa storage/logs/laravel.log para ver el contenido.');
        } else {
            $this->line('Mailer actual: '.config('mail.default'));
        }
    } catch (\Throwable $e) {
        $this->error('❌ Error al enviar: '.$e->getMessage());
        $this->line($e->getTraceAsString());
        return 1;
    }

    return 0;
})->purpose('Enviar un correo RAW de prueba rápido (sin tocar BD)');

// Sincroniza el campo global servicios_empresa.usa_tamanos en base a si existen precios por tamaño
// en al menos un centro. Útil cuando en producción las etiquetas aparecen todas "(Por tamaños)"
// por desalineación histórica entre servicios_empresa y servicios_centro/servicio_tamanos.
// Uso: php artisan servicios:sync-modos
Artisan::command('servicios:sync-modos', function() {
    $this->info('Sincronizando modos de servicios (unitario / por tamaños)...');
    $total = 0; $cAmbioTrue = 0; $cAmbioFalse = 0;
    DB::table('servicios_empresa')->orderBy('id')->chunk(200, function($chunk) use (&$total,&$cAmbioTrue,&$cAmbioFalse) {
        foreach ($chunk as $s) {
            $total++;
            // Ver si hay al menos un registro de servicio_tamanos para este servicio en cualquier centro
            $tieneTamanos = (bool) DB::table('servicios_centro')
                ->join('servicio_tamanos','servicio_tamanos.id_servicio_centro','=','servicios_centro.id')
                ->where('servicios_centro.id_servicio',$s->id)
                ->limit(1)->count();
            if ($tieneTamanos && !$s->usa_tamanos) {
                DB::table('servicios_empresa')->where('id',$s->id)->update(['usa_tamanos'=>1]);
                $cAmbioTrue++;
                $this->line("#{$s->id} -> usa_tamanos=1");
            } elseif (!$tieneTamanos && $s->usa_tamanos) {
                DB::table('servicios_empresa')->where('id',$s->id)->update(['usa_tamanos'=>0]);
                $cAmbioFalse++;
                $this->line("#{$s->id} -> usa_tamanos=0");
            }
        }
    });
    $this->info("Procesados: {$total}. Cambiados a true: {$cAmbioTrue}. Cambiados a false: {$cAmbioFalse}.");
    $this->info('Listo. Refresca la pantalla en producción para ver etiquetas correctas.');
})->purpose('Alinear usa_tamanos según existencia de precios por tamaño');
