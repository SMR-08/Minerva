# Refactor Frontend: Calidad de código (P2)

## 1. Tipado estricto en minerva.service.ts

**Problema**: 15 de 20 métodos devuelven `Observable<any>`.

**Solución**: Usar interfaces existentes + crear las faltantes:
```typescript
// Interfaces que ya existen: Asignatura, Tema, Transcripcion
// Crear:
interface Tag { id_tag: number; nombre: string; color_hex: string; }
interface Segmento { hablante: string; texto: string; inicio: number; fin: number; }
interface PaginatedResponse<T> { data: T[]; current_page: number; last_page: number; total: number; }

// Cambiar:
getTemas(asignaturaId: number): Observable<Tema[]>
crearTema(data: Partial<Tema>): Observable<Tema>
getTags(): Observable<Tag[]>
getTranscripciones(): Observable<Transcripcion[]>
```

---

## 2. Estandarizar template syntax

**Problema**: Mix de `*ngIf`/`*ngFor` (viejo) con `@if`/`@for` (nuevo). 17 instancias del viejo.

**Regla**: Todo a `@if`/`@for`/`@switch` (Angular 17 control flow).

**Archivos**: landing-page, dashboard, transcripcion-view, asignatura-view, formulario-subida, formulario-login, formulario-registro, layout/header.

---

## 3. Loading states

**Implementar** signal `loading = signal(true)` en cada componente smart:
```typescript
loading = signal(true);

ngOnInit() {
  this.minervaService.getAsignaturas().pipe(
    finalize(() => this.loading.set(false))
  ).subscribe(...)
}
```

En template:
```html
@if (loading()) {
  <div class="animate-pulse">...</div>  <!-- skeleton -->
} @else {
  <!-- contenido real -->
}
```

---

## 4. Lazy loading en rutas

**Cambiar** en `app.routes.ts`:
```typescript
// De:
{ path: 'dashboard', component: DashboardComponent }
// A:
{ path: 'dashboard', loadComponent: () => import('./dashboard/dashboard.component').then(m => m.DashboardComponent) }
```

Aplica a TODAS las rutas excepto landing (que es la primera en cargar).

---

## 5. Renombrar SseService a PollingService

**Problema**: El nombre `SseService` es engañoso — usa `setInterval` HTTP polling, no Server-Sent Events.

**Solución**: Renombrar archivo y clase a `polling.service.ts` / `PollingService`.

---

## 6. Accesibilidad básica

**Mínimo viable**:
- `aria-label` en todos los botones de icono (editar, eliminar, cerrar modal)
- `role="dialog"` en modales
- `role="alert"` en notificaciones
- `aria-live="polite"` en zonas que cambian dinámicamente
- Focus trap en modales (o usar CDK `A11yModule`)

---

## Esfuerzo total P2: ~2-3 días
## Riesgo: Bajo (mejoras incrementales, no rompen funcionalidad)
