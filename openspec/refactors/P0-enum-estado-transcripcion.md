# Refactor: EstadoTranscripcion Enum (P0)

## Problema
`Transcripcion::estado` es un string raw usado en 5+ archivos con valores hardcodeados:
- `'SUBIENDO'`, `'ENCOLADO'`, `'PROCESANDO'`, `'COMPLETADO'`, `'FALLIDO'`

Aparece en: SseController, AudioProcessingService, AudioProcessingJob, AdminDashboardController, ProcesamientoAudioController.

## Solución
Crear `app/Enums/EstadoTranscripcion.php`:

```php
<?php

namespace App\Enums;

enum EstadoTranscripcion: string
{
    case SUBIENDO = 'SUBIENDO';
    case ENCOLADO = 'ENCOLADO';
    case PROCESANDO = 'PROCESANDO';
    case COMPLETADO = 'COMPLETADO';
    case FALLIDO = 'FALLIDO';

    public function mensaje(): string
    {
        return match($this) {
            self::SUBIENDO => 'Subiendo audio...',
            self::ENCOLADO => 'En cola de procesamiento',
            self::PROCESANDO => 'Procesando...',
            self::COMPLETADO => 'Transcripción completada',
            self::FALLIDO => 'Error en el procesamiento',
        };
    }

    public function esFinal(): bool
    {
        return in_array($this, [self::COMPLETADO, self::FALLIDO]);
    }
}
```

## Pasos
1. Crear el enum en `app/Enums/EstadoTranscripcion.php`
2. Agregar cast en `Transcripcion.php`: `'estado' => EstadoTranscripcion::class`
3. Reemplazar strings hardcodeados en:
   - `AudioProcessingService.php` (líneas con 'SUBIENDO', 'ENCOLADO', etc.)
   - `AudioProcessingJob.php` ('PROCESANDO', 'FALLIDO')
   - `SseController.php` (switch en estado)
   - `AdminDashboardController.php` (whereIn)
   - `ProcesamientoAudioController.php`
4. Ejecutar `make test-backend` — todos los tests deben pasar

## Esfuerzo: Bajo (1-2h)
## Riesgo: Bajo (cambio mecánico, tests validan)
