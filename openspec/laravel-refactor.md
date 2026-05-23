# Laravel MVC Refactor — Plan de trabajo

> Fecha audit: 2026-05-22
> Estado: PENDIENTE (para developers esta semana)
> Referencia: `AsignaturaController.php` es el patron correcto a seguir

---

## Decisiones del autor

- **TranscripcionService**: ENRIQUECER. Mover logica de ETA, posicion cola, y scopeDelUsuario aqui.
- **AsignaturaService / TemaService**: ELIMINAR. Son CRUD puro, las Policies ya protegen. Controllers usan Models directamente.
- **No usar emojis** en outputs de CLI/Makefile.
- **Reescrituras aceptables** si mejoran la arquitectura. No parches.

---

## Prioridad P0 (hacer primero)

### 1. Crear `app/Enums/EstadoTranscripcion.php`

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
}
```

Agregar cast en `Transcripcion.php`:
```php
protected $casts = [
    'texto_diarizado' => 'array',
    'estado' => EstadoTranscripcion::class,
];
```

Archivos a actualizar (reemplazar strings por enum):
- `SseController.php` (switch en linea 52-77)
- `AudioProcessingService.php` (estados en lineas 36, 42, 65, 72)
- `AudioProcessingJob.php` (estados en lineas 26, 54)
- `AdminDashboardController.php` (whereIn en linea 118)

### 2. Split `SseController.php` (212 lineas → 3 piezas)

| Responsabilidad actual | Mover a |
|----------------------|---------|
| Polling + ETA + posicion cola | `TranscripcionService::obtenerEstado()` |
| Token SSE generation | `SseTokenService` (nuevo) |
| IA push update (sseUpdate) | Mantener en controller, con `SseUpdateRequest` |
| Mensajes por etapa | Metodo en `EstadoTranscripcion` enum |

### 3. Split `AdminDashboardController.php` (247 lineas → servicios)

| Metodo actual | Mover a |
|--------------|---------|
| `index()` stats | `DashboardService::stats()` |
| `testIA()` | `IAHealthService::test()` |
| `uploadAudio()` | `IAHealthService::uploadTest()` |
| `queueStatus()` | `QueueStatusService::getStatus()` |
| `getStorageSize()` | `StorageService::getSize()` |
| `consultarEstadoColaIA()` | `IAHealthService::queueStatus()` |

---

## Prioridad P1 (segunda pasada)

### 4. Query Scope en Transcripcion

```php
// app/Models/Transcripcion.php
public function scopeDelUsuario(Builder $query, Usuario|int $usuario): Builder
{
    $userId = $usuario instanceof Usuario ? $usuario->id_usuario : $usuario;
    return $query->whereHas('tema.asignatura', fn($q) => $q->where('id_usuario', $userId));
}
```

Reemplazar en: TranscripcionService, AudioProcessingService, SseController.

### 5. Route Model Binding faltante

| Controller | Cambio |
|-----------|--------|
| TagController | `Tag $tag` en route + TagPolicy |
| UsuarioController (admin) | `Usuario $usuario` en route |
| SseController (estado) | `Transcripcion::getRouteKeyName() = 'uuid_referencia'` |
| TemaController | Rutas anidadas `asignaturas/{asignatura}/temas` |

### 6. FormRequests nuevos (6)

- `StoreTagRequest` — reemplaza validacion inline en TagController
- `SseUpdateRequest` — reemplaza validacion inline en SseController::sseUpdate
- `ProcesarCallbackRequest` — reemplaza validacion inline en ProcesamientoAudioController
- `AdminLoginRequest` — reemplaza array inline en AdminAuthController
- `AdminStoreUsuarioRequest` — reemplaza array en UsuarioController::store
- `AdminUpdateUsuarioRequest` — reemplaza array en UsuarioController::update

### 7. AuthService

Extraer de AuthController y AdminAuthController:
- `login()`: verificar credenciales, crear HistorialAcceso, actualizar ultimo_acceso, generar token
- Eliminar logica duplicada entre ambos controllers

---

## Prioridad P2 (tercera pasada)

### 8. Policies nuevas

- `TranscripcionPolicy`: owner-check via `tema.asignatura.id_usuario`
- `TagPolicy`: owner-check via `id_usuario`

### 9. Fix N+1 en TemaResource

```php
// Donde se listen temas, agregar:
Tema::withCount('transcripciones')->where(...)
```

### 10. Exception Handler

En `bootstrap/app.php`:
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->renderable(function (ModelNotFoundException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Recurso no encontrado'], 404);
        }
    });
})
```

---

## Prioridad P3 (decisiones)

### 11. Eliminar servicios thin

- Eliminar `AsignaturaService.php` — controller usa Model + Policy directamente
- Eliminar `TemaService.php` — mismo patron
- `TranscripcionService.php` se ENRIQUECE (no se elimina)

---

## Orden de ejecucion recomendado

```
1. Enum EstadoTranscripcion (toca muchos archivos, base para todo)
2. scopeDelUsuario (simplifica queries antes de mover logica)
3. Split SseController (el mas impactante)
4. FormRequests nuevos (mecanico, bajo riesgo)
5. Route Model Binding (mecanico)
6. Split AdminDashboardController
7. AuthService
8. Policies nuevas
9. Eliminar servicios thin
10. Exception Handler
```

Despues de cada paso: `make test-backend` debe seguir verde (55/55).
