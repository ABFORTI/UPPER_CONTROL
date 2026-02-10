# Funcionalidades por Centro (Feature Flags)

Este proyecto implementa un sistema de activación/desactivación de funcionalidades por **centro de trabajo**.

## Concepto

- El catálogo vive en la tabla `features`.
- La asignación por centro vive en la pivote `centro_feature` con un booleano `enabled`.
- La seguridad real se aplica en backend con el middleware `feature:*`.
- El frontend (Inertia/Vue) solo usa `auth.features` para ocultar/mostrar UI.

## Agregar una nueva funcionalidad

1) Insertar una fila en `features` (o agregarla al seeder).
2) Proteger las rutas con middleware:

```php
Route::get('/mi-ruta', ...)->middleware('feature:mi_feature_key');
```

3) (Opcional) Ocultar el menú/botones en UI usando `auth.features`.

## Middleware

Alias: `feature`

Uso:

```php
->middleware('feature:ver_cotizacion')
```

Si el usuario no tiene centro o el centro no tiene habilitada la feature, responde con **403**.

## Ejemplo en Blade (menú)

Si en algún punto se usa Blade para renderizar un menú (fuera de Inertia), el patrón sería:

```php
@if(auth()->user()?->centro?->hasFeature('subir_excel'))
    <a href="{{ route('solicitudes.create') }}">Subir por Excel</a>
@endif
```

> Nota: aunque se oculte el menú, siempre se debe proteger la ruta con el middleware `feature:*`.

## Pantalla Admin

Ruta: `/admin/centros/features`

- Selecciona un centro
- Marca/desmarca features
- Guarda (sin hardcodear IDs)
