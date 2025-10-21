<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class EmailViewer extends Command
{
    protected $signature = 'email:preview {tipo?}';
    protected $description = 'Genera un archivo HTML para previsualizar el email en el navegador';

    public function handle()
    {
        $tipo = $this->argument('tipo') ?? 'ot-asignada';
        
        $this->info('🎨 Generando preview del email tipo: ' . $tipo);
        $this->info('');
        
        // Limpiar log
        File::put(storage_path('logs/laravel.log'), '');
        
        // Generar el email
        $this->call('email:test', ['tipo' => $tipo]);
        
        // Esperar un poco
        sleep(1);
        
        // Leer el log
        $logContent = File::get(storage_path('logs/laravel.log'));
        
        // Buscar diferentes patrones de HTML
        $patterns = [
            '/Content-Type: text\/html.*?\r?\n\r?\n(.*?)(?=\r?\n--)/s',
            '/Content-Type: text\/html.*?\n\n(.*)$/s',
            '/<html.*?<\/html>/s',
            '/<\!DOCTYPE.*?<\/html>/s'
        ];
        
        $htmlContent = null;
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $logContent, $matches)) {
                $htmlContent = $matches[1] ?? $matches[0];
                break;
            }
        }
        
        if (!$htmlContent) {
            // Intentar decodificar todo el log como quoted-printable
            $decoded = quoted_printable_decode($logContent);
            if (preg_match('/<html.*?<\/html>/s', $decoded, $matches)) {
                $htmlContent = $matches[0];
            }
        }
        
        if (!$htmlContent) {
            $this->error('❌ No se pudo extraer el HTML del email');
            $this->info('');
            $this->warn('💡 El log contiene:');
            $this->line(substr($logContent, 0, 500) . '...');
            return 1;
        }
        
        // Decodificar quoted-printable
        $htmlContent = quoted_printable_decode($htmlContent);
        
        // Guardar en archivo
        $previewPath = public_path('email-preview.html');
        File::put($previewPath, $htmlContent);
        
        $url = config('app.url') . '/email-preview.html';
        
        $this->info('');
        $this->info('✅ Preview generado exitosamente!');
        $this->info('');
        $this->info('📄 Archivo: ' . $previewPath);
        $this->info('🌐 URL: ' . $url);
        $this->info('');
        $this->info('💡 Opciones para visualizar:');
        $this->line('  1. Abrir: ' . $previewPath);
        $this->line('  2. O visitar: ' . $url);
        
        return 0;
    }
}
