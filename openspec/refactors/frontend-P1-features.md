# Refactor Frontend: Features faltantes (P1)

## 1. UI de Tags

**Estado**: Backend CRUD completo, service methods existen (`getTags`, `crearTag`, `eliminarTag`), CERO UI.

**Implementar**:
- En `asignatura-view` o `transcripcion-view`: chips de tags con colores
- Modal para crear tag (nombre + color)
- Asignar/desasignar tags a transcripciones (endpoint pivot existe en backend)
- Filtrar transcripciones por tag

**Componentes nuevos**:
- `tag-chip/tag-chip.component.ts` (presentational)
- `tag-selector/tag-selector.component.ts` (dropdown multi-select)

---

## 2. Selector de idioma en upload

**Estado**: `formData.append('idioma', 'auto')` hardcodeado.

**Implementar**:
- Dropdown en formulario-subida con opciones: Auto, Español, Inglés, Francés, Alemán, Portugués, Italiano, Catalán, Euskera, Gallego
- Valor por defecto: 'auto'
- Pasar valor seleccionado al service

---

## 3. Perfil de usuario

**Estado**: No existe ruta, componente, ni service method.

**Implementar**:
- Ruta: `/perfil`
- Componente: `perfil/perfil.component.ts`
- Service method: `getUser()` → `GET /api/user`
- Mostrar: nombre, email, fecha registro, último acceso
- Futuro: cambio de contraseña (requiere endpoint backend)

---

## 4. Indicador de estado IA

**Estado**: `verificarEstadoIA()` existe en service, ningún componente lo usa.

**Implementar**:
- En el HeaderComponent: badge verde/rojo indicando si IA está online
- Llamar `verificarEstadoIA()` al cargar el layout
- Tooltip con detalles (GPU, cola)

---

## 5. Búsqueda funcional

**Estado**: Input de búsqueda existe en dashboard, `onSearch()` está vacío.

**Implementar**:
- Filtrar asignaturas por nombre (client-side, son pocas)
- Filtrar transcripciones por título (client-side o server-side si hay paginación)
- Debounce de 300ms en el input

---

## 6. Página 404

**Estado**: `{ path: '**', redirectTo: '/' }` silencioso.

**Implementar**:
- Componente `not-found/not-found.component.ts`
- Mensaje amigable + link a dashboard
- Ruta: `{ path: '**', component: NotFoundComponent }`

---

## Esfuerzo total P1 Frontend: ~3-4 días
## Prioridad: Tags y progress tracking son los más visibles para el TFG
