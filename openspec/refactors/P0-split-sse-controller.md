# Refactor: Split SseController (P0)

## Problema
`SseController.php` tiene 212 líneas con 4 responsabilidades mezcladas:
1. Endpoint de polling de estado (GET /transcripciones/{uuid}/estado)
2. Cálculo de posición en cola y ETA
3. Gestión de tokens SSE temporales
4. Endpoint de push desde IA (POST /api/ia/sse-update)

## Arquitectura objetivo

```
SseController (solo endpoints HTTP, ~40 líneas)
├── estado()         → usa TranscripcionEstadoService
├── generarTokenSSE() → usa SseTokenService
└── sseUpdate()      → usa SseUpdateRequest + TranscripcionEstadoService

TranscripcionEstadoService (lógica de negocio)
├── obtenerEstado(uuid): array
├── obtenerPosicionCola(transcripcion): int
├── calcularETA(transcripcion): ?int
└── obtenerMensajeEtapa(etapa): string  ← o usar Enum EtapaProcesamiento

SseTokenService
├── generar(usuario): string
└── validar(token): ?Usuario
```

## Pasos

### 1. Crear `app/Enums/EtapaProcesamiento.php`
```php
enum EtapaProcesamiento: string
{
    case INICIANDO = 'INICIANDO';
    case ASR = 'ASR';
    case DIARIZACION = 'DIARIZACION';
    case POSTPROCESAMIENTO = 'POSTPROCESAMIENTO';

    public function mensaje(): string
    {
        return match($this) {
            self::INICIANDO => 'Preparando audio...',
            self::ASR => 'Transcribiendo audio...',
            self::DIARIZACION => 'Identificando hablantes...',
            self::POSTPROCESAMIENTO => 'Procesando resultado...',
        };
    }
}
```

### 2. Crear `app/Services/TranscripcionEstadoService.php`
Mover desde SseController:
- `obtenerPosicionCola()` (líneas 100-113)
- `calcularETA()` (líneas 119-139)
- `obtenerMensajeEtapa()` (líneas 145-153) → reemplazar por Enum
- Lógica del switch de estado (líneas 51-78) → método `construirRespuestaEstado()`

### 3. Crear `app/Services/SseTokenService.php`
Mover desde SseController:
- `generarTokenSSE()` lógica (líneas 168-174)
- Validación de token SSE (líneas 26-39)

### 4. Crear `app/Http/Requests/SseUpdateRequest.php`
Mover validación inline de `sseUpdate()` (líneas 183-189):
```php
public function rules(): array
{
    return [
        'uuid' => 'required|string',
        'estado' => 'required|string',
        'progreso' => 'nullable|integer|min:0|max:100',
        'etapa' => 'nullable|string',
        'mensaje' => 'nullable|string',
        'error' => 'nullable|string',
        'resultado' => 'nullable|array',
    ];
}
```

### 5. Refactorizar SseController
Inyectar servicios, delegar lógica, mantener solo routing HTTP.

### 6. Tests
- `make test-backend` debe pasar sin cambios en tests
- El endpoint `/api/transcripciones/{uuid}/estado` debe devolver mismo JSON

## Esfuerzo: Medio (3-4h)
## Riesgo: Medio (tocar endpoint activo, pero tests cubren)
