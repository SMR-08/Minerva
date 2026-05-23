# Refactor: Split AdminDashboardController (P0)

## Problema
`AdminDashboardController.php` tiene 247 líneas con 5 responsabilidades:
1. Dashboard stats (index)
2. Test de conexión IA (testIA)
3. Upload de audio debug (uploadAudio) — usa endpoint legacy
4. Estado de cola (queueStatus)
5. Cálculo de storage (getStorageSize)

## Arquitectura objetivo

```
AdminDashboardController (~50 líneas, solo routing)
├── index()        → DashboardService::stats()
├── testIA()       → IAHealthService::test()
├── uploadAudio()  → ELIMINAR o mover a AdminAudioController
├── queueStatus()  → QueueStatusService::getFullStatus()
└── debug views    → mantener (son vistas Blade simples)

Servicios nuevos:
├── DashboardService       → stats agregados (counts, últimas transcripciones)
├── IAHealthService        → test conexión, estado cola IA, verificarEstado
└── QueueStatusService     → jobs pendientes, procesando, fallidos, storage
```

## Pasos

### 1. Crear `app/Services/IAHealthService.php`
Mover:
- `testIA()` lógica HTTP (líneas 37-66)
- `consultarEstadoColaIA()` (líneas 222-235)
- `getAIQueueStatus()` (líneas 237-241)
- También mover `verificarEstado()` de ProcesamientoAudioController aquí

```php
class IAHealthService
{
    public function testConnection(): array  // {status, response_time, error?}
    public function getQueueStatus(): array  // {estado, peticiones_en_espera}
    public function getFullHealth(): array   // DB + Redis + IA combined
}
```

### 2. Crear `app/Services/QueueStatusService.php`
Mover:
- `queueStatus()` lógica (líneas 113-198)
- `getStorageSize()` (líneas 200-218)

```php
class QueueStatusService
{
    public function getPendingJobs(): Collection
    public function getProcessingJobs(): Collection
    public function getRecentFailed(): Collection
    public function getStorageUsage(): array  // {size_bytes, formatted}
}
```

### 3. Crear `app/Services/DashboardService.php`
Mover:
- `index()` lógica de stats (líneas 16-27)

```php
class DashboardService
{
    public function getStats(): array
    // total_usuarios, total_transcripciones, total_asignaturas,
    // procesando, completadas_hoy, ultimas_transcripciones
}
```

### 4. Decisión sobre `uploadAudio()`
Este método usa el endpoint legacy `/transcribir_diarizado` que ya no existe en la IA actual.
Opciones:
- A) Eliminarlo (el upload real va por el frontend)
- B) Actualizarlo para usar `/upload` (como AudioProcessingJob)
- C) Moverlo a un `AdminAudioController` separado

Recomendación: opción B si se quiere mantener debug desde admin.

### 5. Tests
- Los tests actuales no cubren admin (son web routes con sesión)
- Verificar manualmente que /admin sigue funcionando

## Esfuerzo: Medio (3-4h)
## Riesgo: Bajo (admin panel, no afecta API pública)
