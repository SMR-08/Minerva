# Implementación: Sistema de Login para Panel de Administración

## 📋 Resumen

Se ha implementado exitosamente un sistema de autenticación completo para el panel de administración de Minerva, protegiendo todas las rutas `/admin/*` con login obligatorio y verificación de permisos de administrador.

## ✅ Componentes Implementados

### 1. Configuración de Autenticación
**Archivo:** `Backend/config/auth.php`
- Configurado el provider `users` para usar el modelo `Usuario` en lugar de `User`
- Guard `web` utiliza sesiones para mantener la autenticación

### 2. Modelo Usuario
**Archivo:** `Backend/app/Models/Usuario.php`
- Modificado para extender `Illuminate\Foundation\Auth\User` (Authenticatable)
- Mantiene compatibilidad con Sanctum para API
- Método `getAuthPassword()` retorna `password_hash`

### 3. Controlador de Autenticación
**Archivo:** `Backend/app/Http/Controllers/Admin/AdminAuthController.php`

**Métodos:**
- `showLoginForm()`: Muestra el formulario de login
- `login()`: Procesa credenciales, verifica rol admin, crea sesión
- `logout()`: Cierra sesión y redirige al login

**Características:**
- Validación de credenciales
- Verificación de rol admin (id_rol === 1)
- Logout automático si el usuario no es admin
- Actualización de `ultimo_acceso`
- Soporte para "Recordar sesión"
- Intended redirect (redirige a la página intentada después del login)

### 4. Vista de Login
**Archivo:** `Backend/resources/views/admin/login.blade.php`

**Características:**
- Diseño minimalista con glass effect
- Consistente con el estilo del panel admin
- Validación de errores en tiempo real
- Campos: email, password, remember
- Responsive y accesible

### 5. Página de Error 403
**Archivo:** `Backend/resources/views/errors/403.blade.php`

**Características:**
- Mensaje claro: "No tienes permisos de administrador"
- Botón para cerrar sesión (si está autenticado)
- Botón para ir al login (si no está autenticado)
- Diseño consistente con el panel

### 6. Rutas Protegidas
**Archivo:** `Backend/routes/web.php`

**Rutas públicas:**
- `GET /admin/login` - Formulario de login
- `POST /admin/login` - Procesar login

**Rutas protegidas** (middleware: `auth:web`, `es_admin`):
- `POST /admin/logout` - Cerrar sesión
- `GET /admin` - Dashboard
- `GET /admin/debug` - Consola de debug
- `POST /admin/debug/test-ia` - Test IA
- `POST /admin/debug/upload-audio` - Upload audio
- `GET /admin/usuarios` - Gestión de usuarios
- `POST /admin/usuarios` - Crear usuario
- `PUT /admin/usuarios/{id}` - Actualizar usuario
- `DELETE /admin/usuarios/{id}` - Eliminar usuario

### 7. Middleware EsAdmin Mejorado
**Archivo:** `Backend/app/Http/Middleware/EsAdmin.php`

**Mejoras:**
- Detecta si es petición API o Web
- API: retorna JSON con error 403
- Web: muestra página 403 personalizada
- Verifica `id_rol === 1`

### 8. Layout Admin Actualizado
**Archivo:** `Backend/resources/views/layouts/admin.blade.php`

**Cambios:**
- Botón "Cerrar Sesión" en el sidebar
- Muestra iniciales del usuario autenticado
- Form POST para logout con CSRF token

### 9. Configuración de Redirects
**Archivo:** `Backend/bootstrap/app.php`

**Configuración:**
- `redirectGuestsTo()` configurado para redirigir a `route('admin.login')`
- Usuarios no autenticados son redirigidos automáticamente

## 🧪 Pruebas Realizadas

### Test 1: Acceso sin autenticación ✅
- **Acción:** Acceder a `/admin` sin login
- **Resultado:** Redirige a `/admin/login` (HTTP 302)
- **Estado:** PASS

### Test 2: Login con credenciales de admin ✅
- **Acción:** Login con `minerva@mail.com` / `admin123`
- **Resultado:** Redirige a `/admin` (dashboard)
- **Estado:** PASS

### Test 3: Navegación en panel autenticado ✅
- **Acción:** Navegar por dashboard, usuarios, debug
- **Resultado:** Todas las páginas cargan correctamente
- **Estado:** PASS

### Test 4: Logout ✅
- **Acción:** Click en "Cerrar Sesión"
- **Resultado:** Redirige a `/admin/login` con mensaje de éxito
- **Estado:** PASS

### Test 5: Intended Redirect ✅
- **Acción:** Acceder a `/admin/usuarios` sin login, luego hacer login
- **Resultado:** Después del login, redirige a `/admin/usuarios`
- **Estado:** PASS

### Test 6: Usuario no-admin ✅
- **Acción:** Login con `usuario@test.com` / `user123` (id_rol=2)
- **Resultado:** Login rechazado con mensaje "No tienes permisos de administrador"
- **Estado:** PASS

### Test 7: Protección de rutas ✅
- **Acción:** Verificar que todas las rutas `/admin/*` requieren autenticación
- **Resultado:** Todas las rutas protegidas retornan 302 sin autenticación
- **Estado:** PASS

## 📊 Estadísticas

- **Archivos creados:** 3
- **Archivos modificados:** 6
- **Rutas protegidas:** 9
- **Tests ejecutados:** 7
- **Tests exitosos:** 7
- **Screenshots capturados:** 12

## 🔐 Credenciales de Prueba

### Usuario Administrador
- **Email:** minerva@mail.com
- **Password:** admin123
- **Rol:** ADMIN (id_rol = 1)

### Usuario Normal (para testing)
- **Email:** usuario@test.com
- **Password:** user123
- **Rol:** USUARIO (id_rol = 2)

## 🚀 Cómo Usar

### Para Administradores:
1. Acceder a `http://localhost:8001/admin`
2. Serás redirigido a `/admin/login`
3. Ingresar credenciales de admin
4. Acceder al panel completo

### Para Desarrolladores:
```bash
# Crear nuevo usuario admin
docker compose exec laravel-app php artisan tinker
$user = Usuario::create([
    'nombre_completo' => 'Nuevo Admin',
    'email' => 'admin@example.com',
    'password_hash' => Hash::make('password'),
    'id_rol' => 1,
    'id_estado' => 1,
    'ultimo_acceso' => now()
]);
```

## 🔧 Configuración Adicional

### Variables de Entorno
No se requieren variables adicionales. El sistema usa la configuración existente de Laravel.

### Base de Datos
La tabla `usuarios` debe tener:
- `id_rol`: 1 para admin, 2 para usuario normal
- `id_estado`: 1 para activo
- `password_hash`: Hash bcrypt de la contraseña

### Sesiones
El sistema usa sesiones de base de datos (`SESSION_DRIVER=database`).

## 📝 Notas Importantes

1. **Seguridad:**
   - Las contraseñas se hashean con bcrypt (12 rounds)
   - CSRF protection habilitado en todos los formularios
   - Sesiones regeneradas después del login
   - Logout invalida la sesión completamente

2. **Compatibilidad:**
   - El sistema API (Sanctum) sigue funcionando independientemente
   - El middleware `es_admin` detecta automáticamente API vs Web

3. **Mantenimiento:**
   - Los logs de Laravel registran intentos de login
   - El campo `ultimo_acceso` se actualiza en cada login exitoso

## 🐛 Troubleshooting

### Problema: "Route [login] not defined"
**Solución:** Ejecutar `php artisan route:clear && php artisan config:clear`

### Problema: Redirect loop
**Solución:** Verificar que `bootstrap/app.php` tenga configurado `redirectGuestsTo()`

### Problema: 403 en todas las rutas
**Solución:** Verificar que el usuario tenga `id_rol = 1` en la base de datos

## ✨ Próximos Pasos (Opcional)

1. **Recuperación de contraseña:** Implementar "Olvidé mi contraseña"
2. **2FA:** Añadir autenticación de dos factores
3. **Logs de auditoría:** Registrar acciones de admin
4. **Rate limiting:** Limitar intentos de login
5. **Notificaciones:** Email al admin cuando hay login

## 📸 Screenshots

Los screenshots de las pruebas están disponibles en `/tmp/`:
- `minerva-login-filled.png` - Formulario de login
- `minerva-dashboard.png` - Dashboard después del login
- `minerva-after-logout.png` - Página de login después del logout
- `minerva-usuarios-page.png` - Página de gestión de usuarios
- `minerva-intended-success.png` - Intended redirect funcionando

---

**Fecha de implementación:** 10 de Marzo, 2026  
**Desarrollado por:** OpenCode AI Assistant  
**Estado:** ✅ Completado y Testeado
