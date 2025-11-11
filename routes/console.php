<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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
