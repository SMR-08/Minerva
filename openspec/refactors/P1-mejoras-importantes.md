# Refactors P1: Mejoras importantes

## 1. Query Scope `scopeDelUsuario` en Transcripcion

**Problema**: `whereHas('tema.asignatura', fn($q) => $q->where('id_usuario', ...))` repetido 4+ veces.

**Solución** en `app/Models/Transcripcion.php`:
```php
public function scopeDelUsuario(Builder $query, Usuario|int $usuario): Builder
{
    $id = $usuario instanceof Usuario ? $usuario->id_usuario : $usuario;
    return $query->whereHas('tema.asignatura', fn($q) => $q->where('id_usuario', $id));
}
```

**Uso**: `Transcripcion::delUsuario($user)->where(...)`

**Archivos a actualizar**: TranscripcionService, AudioProcessingService, SseController.

---

## 2. Route Model Binding completo

**Falta en**:
- `TagController`: `Tag::where('id_tag', $id)->...->firstOrFail()` → usar binding + Policy
- `TemaController`: `Asignatura::findOrFail($peticion->asignatura_id)` → rutas anidadas `asignaturas/{asignatura}/temas`
- `Admin/UsuarioController`: `Usuario::findOrFail($id)` → `Usuario $usuario` en firma
- `SseController`: `Transcripcion::where('uuid_referencia', $uuid)->firstOrFail()` → agregar `getRouteKeyName()` al modelo:

```php
// Transcripcion.php
public function getRouteKeyName(): string
{
    return 'uuid_referencia';
}
```

---

## 3. FormRequests faltantes (6 nuevos)

| Request | Controller | Validación actual |
|---------|-----------|-------------------|
| `StoreTagRequest` | TagController::store | inline `$peticion->validate(...)` |
| `SseUpdateRequest` | SseController::sseUpdate | inline `$request->validate(...)` |
| `ProcesarCallbackRequest` | ProcesamientoAudioController::procesarCallback | inline |
| `AdminLoginRequest` | AdminAuthController::login | inline con mensajes custom |
| `AdminStoreUserRequest` | UsuarioController::store | array `$reglas` + `$mensajes` |
| `AdminUpdateUserRequest` | UsuarioController::update | array `$reglas` + `$mensajes` |

---

## 4. AuthService (extraer lógica de login)

**Problema**: AuthController y AdminAuthController ambos hacen:
- `HistorialAcceso::create([...])`
- `$usuario->update(['ultimo_acceso' => now()])`

**Solución**: `app/Services/AuthService.php`:
```php
class AuthService
{
    public function registrarAcceso(Usuario $usuario, Request $request): void
    {
        HistorialAcceso::create([
            'id_usuario' => $usuario->id_usuario,
            'fecha_acceso' => now(),
            'ip_acceso' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        $usuario->update(['ultimo_acceso' => now()]);
    }
}
```

---

## Esfuerzo total P1: ~4-6h
## Riesgo: Bajo (tests existentes validan)
