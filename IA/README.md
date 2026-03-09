# Documentación del Backend IA (ARS) v2.0

## ¿Qué es este sistema?

El **ARS (Automated Recognition System)** es un microservicio contenerizado que procesa archivos de audio para:
1.  **Transcribir**: Convertir voz a texto usando **Qwen3-ASR-1.7B** (IA de Alibaba Cloud, SOTA en ASR multi-idioma).
2.  **Diarizar**: Identificar "quién habla cuándo" con **Senko** (diarización ultra-rápida, 1h en ~5s).
3.  **Asignar Roles**: Clasificar automáticamente a los hablantes como "Profesor" o "Alumnos" basándose en la dominancia de la voz.
4.  **Alineación Inteligente**: Fusionar la transcripción con la diarización para generar un guion conversacional coherente.

## Modelos IA

| Componente | Modelo | Descripción |
|---|---|---|
| **ASR** | `Qwen/Qwen3-ASR-1.7B` | Transcripción de voz, 52 idiomas, SOTA open-source |
| **Timestamps** | `Qwen/Qwen3-ForcedAligner-0.6B` | Timestamps palabra a palabra de alta precisión |
| **Diarización** | Senko (CAM++ embeddings) | ~17x más rápido que Pyannote, agnóstico al idioma |

## Configuración Rápida

El sistema funciona con Docker. Si necesitas levantarlo localmente:

1.  Asegúrate de tener un archivo `.env` configurado (ver `.env.example`).
2.  Coloca los audios a procesar en la carpeta configurada como **Entrada** (por defecto `./audios/entrada`).
3.  Arranca el servicio:
    ```bash
    docker-compose up -d
    ```
4.  La API estará disponible en `http://localhost:8000` de donde se despliegue.

---

## 📚 Documentación Interactiva (Swagger/OpenAPI)

**¡Recomendado!** Para ver los esquemas de datos exactos, tipos de variables y probar la API directamente desde el navegador:

- Visita: **`http://localhost:8000/docs`**

Allí encontrarás:
- **Esquemas JSON completos** (Request/Response) autogenerados.
- **Tipos de datos** (String, Float, Boolean).
- **Botón "Try it out"** para lanzar peticiones reales sin escribir código.

---

## Referencia de la API

### 1. Verificar Estado del Servicio
Comprueba si la API está lista y si la GPU está disponible.

- **Endpoint**: `GET /estado`
- **Respuesta Exitosa**:
  ```json
  {
    "estado": "API ARS activa",
    "gpu": "NVIDIA GeForce RTX 4090"
  }
  ```

### 2. Consultar Estado de la Cola
El sistema procesa los audios pesados de uno en uno para no saturar la GPU. Consulta cuántos trabajos hay en espera.

- **Endpoint**: `GET /estado_cola`
- **Respuesta**:
  ```json
  {
    "estado": "ocupado",
    "peticiones_en_espera": 2
  }
  ```
- `estado`: "libre" u "ocupado".

### 3. Procesar Audio (Transcripción + Diarización)
Este es el endpoint principal. Inicia el flujo completo de procesamiento.
**IMPORTANTE**: El archivo de audio YA debe existir en la carpeta de entrada (`./audios/entrada` en el host) antes de llamar a este endpoint.

- **Endpoint**: `POST /transcribir_diarizado`
- **Content-Type**: `application/json`

#### Cuerpo de la Petición (JSON)

| Campo | Tipo | Obligatorio | Descripción | Default |
| :--- | :--- | :--- | :--- | :--- |
| `nombre_archivo` | `string` | **SÍ** | Nombre exacto del archivo en la carpeta de entrada (ej: "clase_matematicas.wav"). | - |
| `idioma` | `string` | No | Código ISO del idioma (ej: "es", "en") o "auto" para detección automática. | "auto" |
| `activar_suavizado` | `bool` | No | Activa la lógica "Zero Gap" para unir frases cortas y continuas. | `true` |
| `activar_correccion_desconocidos` | `bool` | No | Intenta deducir hablantes desconocidos por contexto. | `true` |
| `activar_asignacion_roles` | `bool` | No | Asigna automáticamente "Profesor" al hablante principal. | `true` |

**Ejemplo de Petición:**
```json
{
  "nombre_archivo": "audio_test_01.wav",
  "idioma": "es",
  "activar_asignacion_roles": true
}
```

#### Respuesta (JSON)

Devuelve el objeto de la conversación procesada.

```json
{
  "estado": "exito",
  "uuid_asr": "c8f2a1...",
  "metricas_rendimiento": {
    "tiempo_procesamiento_total_segundos": 45.2,
    "duracion_audio_segundos": 120.0,
    "factor_tiempo_real_porcentaje": 37.6,
    "tiempos_etapa_segundos": {
      "asr": 15.1,
      "diarizacion": 20.4,
      "alineacion": 0.5,
      ...
    }
  },
  "transcripcion": [
    {
      "hablante": "Profesor",
      "inicio": 0.0,
      "fin": 5.4,
      "texto": "Buenos días a todos, hoy vamos a ver integrales."
    },
    {
      "hablante": "Alumnos",
      "inicio": 5.8,
      "fin": 7.2,
      "texto": "¿Entra en el examen?"
    },
    {
      "hablante": "Profesor",
      "inicio": 7.5,
      "fin": 12.0,
      "texto": "Sí, por supuesto que entra. Abrid el libro."
    }
  ]
}
```

## Notas Adicionales

- **Archivos de Salida**: Además de la respuesta JSON, el sistema guarda físicamente los resultados en la carpeta de salida (`./audios/salida` en el host) dentro de una subcarpeta con el ID único.
- **Concurrencia**: Si lanzas múltiples peticiones simultáneas, el sistema las pondrá en cola y las ejecutará secuencialmente. El cliente HTTP debe esperar (timeout alto recomendado).
- **Formato de Audio**: Senko requiere WAV 16kHz mono 16-bit. El sistema convierte automáticamente cualquier formato de entrada usando `ffmpeg`.
- **Sin Token HF**: A diferencia de la versión anterior con Pyannote, ya **no se necesita token de HuggingFace** para el diarizador. Los modelos Qwen3-ASR se descargan automáticamente.
