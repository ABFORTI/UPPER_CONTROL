<?php

namespace Database\Seeders;

use App\Models\Feature;
use Illuminate\Database\Seeder;

class FeatureSeeder extends Seeder
{
    /**
     * Seed inicial del catálogo de features.
     *
     * Puedes agregar nuevas funcionalidades añadiendo nuevas filas aquí (o desde BD).
     * La lógica del sistema se basa en `key`, no en IDs.
     */
    public function run(): void
    {
        Feature::updateOrCreate(
            ['key' => 'ver_cotizacion'],
            [
                'nombre' => 'Ver cotizaciones',
                'descripcion' => 'Permite acceder a las pantallas y rutas de Cotizaciones.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'subir_excel'],
            [
                'nombre' => 'Subir solicitudes por Excel',
                'descripcion' => 'Permite subir/parsear Excel para precargar solicitudes y descargar Excel origen.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'subir_excel_productos'],
            [
                'nombre' => 'Carga masiva de solicitudes por Excel',
                'descripcion' => 'Permite subir Excel con columnas PO, SKU, VPN, Marca, QTY, Pedimento y Notas para crear una solicitud con múltiples registros.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'service_customs_fields'],
            [
                'nombre' => 'Campos aduanales (SKU/Origen/Pedimento)',
                'descripcion' => 'Habilita captura de SKU, Origen y Pedimento en Información del Servicio.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'avance_contenedor_folio'],
            [
                'nombre' => 'Contenedor / Folio en avances',
                'descripcion' => 'Permite capturar número de contenedor o folio al registrar avances y mostrarlo en el historial del servicio.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'revision_cliente_no_autoriza'],
            [
                'nombre' => 'Revisión cuando cliente no autoriza',
                'descripcion' => 'Permite al cliente solicitar revisión con comentario y fotos para que coordinación revise por qué no autoriza una OT.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'omitir_calidad_y_enviar_a_cliente'],
            [
                'nombre' => 'Omitir calidad y enviar a autorización del cliente',
                'descripcion' => 'Omite la revisión de calidad al completar una OT y la envía directo a autorización del cliente para ese almacén/centro.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'solicitud_formulario_solo_servicio'],
            [
                'nombre' => 'Formulario de solicitud solo con servicio',
                'descripcion' => 'Oculta en Solicitudes/Create los campos centro de costos, marca, descripción y área para el almacén/centro.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'solicitud_ocultar_centro_costo'],
            [
                'nombre' => 'Ocultar centro de costos en solicitud',
                'descripcion' => 'Oculta el campo Centro de Costos en el formulario de creación de solicitudes para el almacén/centro.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'solicitud_ocultar_marca'],
            [
                'nombre' => 'Ocultar marca en solicitud',
                'descripcion' => 'Oculta el campo Marca en el formulario de creación de solicitudes para el almacén/centro.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'solicitud_ocultar_descripcion'],
            [
                'nombre' => 'Ocultar descripción en solicitud',
                'descripcion' => 'Oculta los campos de descripción en el formulario de creación de solicitudes para el almacén/centro.',
            ]
        );

        Feature::updateOrCreate(
            ['key' => 'solicitud_ocultar_area'],
            [
                'nombre' => 'Ocultar área en solicitud',
                'descripcion' => 'Oculta el campo Área en el formulario de creación de solicitudes para el almacén/centro.',
            ]
        );
    }
}
