# Verificación Manual — PR #11 (Ajustes de Usuario)

## Objetivo
Verificar que las APIs de gestión de usuario (`/api/user/profile`, `/api/user/password`, `/api/user`) funcionan correctamente end-to-end.

---

## Pre-requisitos

1. **Levantar entorno de desarrollo:**
   ```bash
   cd ~/Minerva
   make dev-up
   ```

2. **Verificar que los servicios están corriendo:**
   ```bash
   docker ps | grep -E "laravel-app|minerva-db|minerva-redis"
   ```

3. **Crear un usuario de prueba (si no existe):**
   ```bash
   curl -X POST http://localhost:8001/api/register \
     -H "Content-Type: application/json" \
     -d '{
       "nombre": "Test User",
       "usuario": "test@example.com",
       "password": "password123",
       "password_confirmation": "password123"
     }'
   ```

4. **Obtener token de autenticación:**
   ```bash
   TOKEN=$(curl -s -X POST http://localhost:8001/api/login \
     -H "Content-Type: application/json" \
     -d '{
       "usuario": "test@example.com",
       "password": "password123"
     }' | jq -r '.token')
   
   echo "Token: $TOKEN"
   ```

---

## Tests Manuales

### 1. Actualizar Perfil (PATCH /api/user/profile)

**Caso exitoso:**
```bash
curl -X PATCH http://localhost:8001/api/user/profile \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre_completo": "Test User Updated",
    "email": "testupdated@example.com"
  }'
```

**Resultado esperado:**
```json
{
  "message": "Perfil actualizado correctamente",
  "usuario": {
    "id": 1,
    "nombre_completo": "Test User Updated",
    "usuario": "testupdated@example.com"
  }
}
```

**Verificar en base de datos:**
```bash
docker exec minerva-db mysql -u minerva -pminerva_pass minerva \
  -e "SELECT id_usuario, nombre_completo, email FROM usuarios WHERE email='testupdated@example.com';"
```

**Caso de error (email duplicado):**
```bash
# Primero crear otro usuario
curl -X POST http://localhost:8001/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Another User",
    "usuario": "another@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Intentar actualizar con email existente
curl -X PATCH http://localhost:8001/api/user/profile \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre_completo": "Test User",
    "email": "another@example.com"
  }'
```

**Resultado esperado:** HTTP 422 con mensaje de error de validación.

---

### 2. Cambiar Contraseña (PATCH /api/user/password)

**Caso exitoso:**
```bash
curl -X PATCH http://localhost:8001/api/user/password \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "password_actual": "password123",
    "password_nuevo": "newpassword456",
    "password_nuevo_confirmation": "newpassword456"
  }'
```

**Resultado esperado:**
```json
{
  "message": "Contraseña actualizada correctamente"
}
```

**Verificar que la nueva contraseña funciona:**
```bash
NEW_TOKEN=$(curl -s -X POST http://localhost:8001/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "usuario": "testupdated@example.com",
    "password": "newpassword456"
  }' | jq -r '.token')

echo "Nuevo token: $NEW_TOKEN"
```

**Caso de error (contraseña actual incorrecta):**
```bash
curl -X PATCH http://localhost:8001/api/user/password \
  -H "Authorization: Bearer *** \
  -H "Content-Type: application/json" \
  -d '{
    "password_actual": "wrongpassword",
    "password_nuevo": "anotherpassword789",
    "password_nuevo_confirmation": "anotherpassword789"
  }'
```

**Resultado esperado:** HTTP 422 con mensaje "La contraseña actual es incorrecta".

---

### 3. Eliminar Cuenta (DELETE /api/user)

**Caso exitoso:**
```bash
curl -X DELETE http://localhost:8001/api/user \
  -H "Authorization: Bearer $NEW_TOKEN"
```

**Resultado esperado:**
```json
{
  "message": "Cuenta eliminada correctamente"
}
```

**Verificar que el usuario fue eliminado:**
```bash
docker exec minerva-db mysql -u minerva -pminerva_pass minerva \
  -e "SELECT id, nombre, usuario FROM usuarios WHERE usuario='testupdated@example.com';"
```

**Resultado esperado:** Sin resultados (usuario eliminado).

**Verificar que el token ya no funciona:**
```bash
curl -X GET http://localhost:8001/api/user \
  -H "Authorization: Bearer $NEW_TOKEN"
```

**Resultado esperado:** HTTP 401 Unauthorized.

---

## Checklist de Verificación

- [ ] PATCH /api/user/profile actualiza nombre y email correctamente
- [ ] PATCH /api/user/profile rechaza emails duplicados con error 422
- [ ] PATCH /api/user/password cambia la contraseña correctamente
- [ ] PATCH /api/user/password rechaza contraseña actual incorrecta con error 422
- [ ] DELETE /api/user elimina la cuenta y todas sus relaciones (asignaturas, temas, transcripciones)
- [ ] DELETE /api/user invalida el token de autenticación
- [ ] Frontend en http://localhost:4200/ajustes muestra los formularios correctamente
- [ ] Frontend muestra notificaciones de éxito/error apropiadas
- [ ] Frontend redirige a /login tras eliminar cuenta

---

## Limpieza

```bash
make dev-down
```

---

## Notas

- Todos los endpoints requieren autenticación Bearer token (Sanctum).
- Las validaciones client-side (Angular) son complementarias a las server-side (Laravel).
- La eliminación de cuenta es en cascada: elimina asignaturas, temas, transcripciones y tags del usuario.
- Los tests automatizados en `ajustes.component.spec.ts` cubren la lógica del componente, pero esta verificación manual valida la integración completa.
