# Refactors P2-P3: Buenas prácticas y decisiones

## P2: Buenas prácticas

### 1. TranscripcionPolicy + TagPolicy

**Problema**: No hay policy para Transcripcion ni Tag. La autorización se hace con scoped queries manuales.

**Crear** `app/Policies/TranscripcionPolicy.php`:
```php
class TranscripcionPolicy
{
    public function view(Usuario $usuario, Transcripcion $transcripcion): bool
    {
        return $transcripcion->tema->asignatura->id_usuario === $usuario->id_usuario;
    }
    // update, delete: mismo patrón
}
```

**Crear** `app/Policies/TagPolicy.php`:
```php
class TagPolicy
{
    public function delete(Usuario $usuario, Tag $tag): bool
    {
        return $tag->id_usuario === $usuario->id_usuario;
    }
}
```

**Registrar** en `AuthServiceProvider` o auto-discovery.

---

### 2. withCount para N+1 en TemaResource

**Problema**: `TemaResource` hace `$this->transcripciones()->count()` → query por cada tema.

**Fix** en TemaService/Controller:
```php
$temas = Tema::where('id_asignatura', $id)->withCount('transcripciones')->get();
```

En TemaResource:
```php
'total_transcripciones' => $this->transcripciones_count,
```

---

### 3. Custom Exception Handler

**Problema**: `bootstrap/app.php` tiene `withExceptions()` vacío. Errores se manejan con try/catch manual en 5 sitios.

**Solución** en `bootstrap/app.php`:
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->renderable(function (ModelNotFoundException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'Recurso no encontrado'], 404);
        }
    });

    $exceptions->renderable(function (AuthenticationException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json(['message' => 'No autenticado'], 401);
        }
    });
})
```

---

## P3: Decisiones pendientes

### Servicios thin: ¿enriquecer o eliminar?

| Servicio | Líneas | Lógica real | Recomendación |
|----------|--------|-------------|---------------|
| AsignaturaService | 33 | Ninguna (proxy Eloquent) | ELIMINAR — Policy protege, controller usa Model directo |
| TemaService | 35 | Ninguna (proxy Eloquent) | ELIMINAR — mismo caso |
| TranscripcionService | 41 | Ninguna | ENRIQUECER — mover scopeDelUsuario, ETA, cola aquí |

**Si se eliminan** AsignaturaService y TemaService:
- AsignaturaController usa `Asignatura::create()`, `$asignatura->update()`, `$asignatura->delete()` directamente
- La Policy sigue protegiendo ownership
- El controller sigue siendo thin porque FormRequest valida y Resource formatea

**Si se enriquece** TranscripcionService:
- Absorbe `obtenerPosicionCola()`, `calcularETA()` de SseController
- Absorbe `scopeDelUsuario()` como método del service
- Se convierte en el service real de dominio para transcripciones

---

## Orden de ejecución recomendado

```
Día 1: P0 — Enum + Split SseController + Split AdminDashboard
Día 2: P1 — Scopes + Route Model Binding + FormRequests + AuthService
Día 3: P2 — Policies + withCount + Exception Handler
Día 3: P3 — Decisión sobre thin services (5 min de discusión, 30 min de implementar)
```

Total estimado: 2-3 días de trabajo para un developer.
Tests existentes (55) validan que nada se rompe en cada paso.
