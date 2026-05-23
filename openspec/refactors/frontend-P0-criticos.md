# Refactor Frontend: Criticos (P0)

## 1. Activar LayoutComponent como shell

**Problema**: `layout/layout.component.ts` existe con `<app-header>`, `<router-outlet>`, `<app-footer>` pero NUNCA se usa. Cada componente (dashboard, asignatura-view, transcripcion-view, formulario-subida) duplica header, footer y user menu (~200 líneas de HTML repetido por componente).

**Solución**:
1. En `app.routes.ts`, envolver rutas protegidas en un layout route:
```typescript
{
  path: '',
  component: LayoutComponent,
  canActivate: [AuthGuard],
  children: [
    { path: 'dashboard', loadComponent: () => import('./dashboard/dashboard.component').then(m => m.DashboardComponent) },
    { path: 'asignatura/:id', loadComponent: () => import('./asignatura-view/asignatura-view.component').then(m => m.AsignaturaViewComponent) },
    { path: 'transcripcion/:id', loadComponent: () => import('./transcripcion-view/transcripcion-view.component').then(m => m.TranscripcionViewComponent) },
    { path: 'subir', loadComponent: () => import('./formulario-subida/formulario-subida.component').then(m => m.FormularioSubidaComponent) },
  ]
}
```
2. Eliminar header/footer/user-menu duplicado de cada componente
3. Mover lógica de user menu al HeaderComponent

**Beneficio**: Elimina ~800 líneas de HTML duplicado + activa lazy loading.

---

## 2. Implementar paginación

**Problema**: `getTranscripciones()` y `getAsignaturas()` devuelven TODOS los registros. Con 100+ transcripciones el frontend se cuelga.

**Solución**:
1. Backend: agregar `?page=1&per_page=20` a los endpoints (Laravel Paginator ya lo soporta)
2. Frontend: agregar params a las llamadas del service
3. Componente: botones "Cargar más" o paginación numérica

---

## 3. Eliminar console.error (12 instancias)

**Archivos afectados**: dashboard, formulario-subida, formulario-login, formulario-registro, sse.service

**Reemplazar por**:
```typescript
this.notificationService.error('Error al cargar asignaturas');
```

---

## 4. Arreglar progress tracking post-upload

**Problema**: `conectarSSE()` y `manejarEventoSSE()` están definidos en formulario-subida pero NUNCA se llaman. Después del upload exitoso, el componente navega inmediatamente sin mostrar progreso.

**Solución**:
1. Después de upload exitoso, NO navegar
2. Llamar `conectarSSE(uuid)` para iniciar polling
3. Mostrar barra de progreso con etapa actual
4. Navegar a transcripcion-view solo cuando estado = COMPLETADO

---

## 5. Eliminar dead code

| Archivo/Directorio | Acción |
|-------------------|--------|
| `dashboard/dashboard.module.ts` | Eliminar |
| `carpetas/carpetas.component.ts` | Eliminar |
| `dashboard/interfaces/` (duplicados) | Eliminar |
| `layout/` components | MANTENER (se activan en punto 1) |

---

## Esfuerzo total P0 Frontend: ~2 días
## Riesgo: Medio (cambios visuales, requiere testing manual)
