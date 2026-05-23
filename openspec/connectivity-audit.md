# Minerva Connectivity Audit (Backend ↔ IA)

Date: 2026-05-21
Project path: `/home/mht/Documentos/2-DAW/Minerva`
Scope: REAL end-to-end test (audio upload API → queue worker → IA → callback)

---

## Environment

- Compose file used: `docker-compose.yml`
- API entrypoint used from host: `http://localhost:8001/api/...`
- Callback secret configured: `cambiar_por_secret_generado_con_openssl_rand_base64_32`

---

## Step-by-step execution

### 1) Seed database
Command:
- `docker compose -f docker-compose.yml exec -T laravel-app php artisan db:seed`

Result:
- `INFO  Seeding database.`

Status: ✅ Success

---

### 2) Get auth token (tinker)
Command:
- `docker compose -f docker-compose.yml exec -T laravel-app php artisan tinker --execute="echo App\\Models\\Usuario::where('email','admin@minerva.com')->first()->createToken('connectivity-audit')->plainTextToken;"`

Result:
- Token created (`39|5kl9yUi9nGptgOW4zv5AazpfTQy4ySNsn2tKwpi4acc2e5aa`)

Status: ✅ Success

---

### 3) Create test data (Asignatura + Tema)
API calls made with Bearer token.

Create Asignatura response:
```json
{"id_asignatura":34,"nombre":"Audit Asignatura","profesor":null,"color_hex":"#3366FF","icono":null,"descripcion":"Connectivity audit","semestre":null,"num_temas":0,"fecha_creacion":null}
```

Create Tema response:
```json
{"id_tema":20,"nombre":"Audit Tema","descripcion":null,"orden":null,"id_asignatura":34,"num_transcripciones":0}
```

Status: ✅ Success

---

### 4) Generate test WAV file (real signal, non-silence)
Method:
- Python generated mono 16kHz, 3s tone (440Hz + 660Hz), file size 96044 bytes.

File:
- `.tmp-audit/test-tone.wav`

Status: ✅ Success

---

### 5) Upload audio via API
Endpoint:
- `POST /api/temas/{id}/procesar-audio`

Response:
```json
{"uuid":"63207777-2d54-4fd3-8f73-f9b2ec307782","estado":"ENCOLADO","message":"Archivo subido. Procesando en cola..."}
```

Status: ✅ Success (enqueue accepted)

---

### 6) Monitor processing status + worker/asr logs
Polling endpoint:
- `GET /api/transcripciones/{uuid}/estado?token={sanctum_token}`

Observed timeline:
- 20:47:44Z → `PROCESANDO`, etapa `INICIANDO`, progreso `0`
- Remains in same state across polling window
- Final state after retries:
```json
{"estado":"FALLIDO","titulo":"Audit upload","uuid":"63207777-2d54-4fd3-8f73-f9b2ec307782","error":"Error tras 3 intentos: Unable to read from stream","mensaje":"Error al procesar"}
```

Status: ❌ Failed

---

## Critical evidence (exact errors)

From `storage/logs/laravel.log`:

- `[2026-05-21 20:47:34] local.INFO: AudioProcessingJob intento 1/3 para 63207777-2d54-4fd3-8f73-f9b2ec307782`
- `[2026-05-21 20:47:34] local.ERROR: AudioProcessingJob error para 63207777-2d54-4fd3-8f73-f9b2ec307782: Unable to read from stream`
- `[2026-05-21 20:48:34] local.INFO: AudioProcessingJob intento 2/3 para 63207777-2d54-4fd3-8f73-f9b2ec307782`
- `[2026-05-21 20:48:34] local.ERROR: AudioProcessingJob error para 63207777-2d54-4fd3-8f73-f9b2ec307782: Unable to read from stream`
- `[2026-05-21 20:53:34] local.INFO: AudioProcessingJob intento 3/3 para 63207777-2d54-4fd3-8f73-f9b2ec307782`
- `[2026-05-21 20:53:34] local.ERROR: AudioProcessingJob error para 63207777-2d54-4fd3-8f73-f9b2ec307782: Unable to read from stream`

From `laravel-worker` logs:
- `2026-05-21 20:47:33 App\Jobs\AudioProcessingJob RUNNING`
- `2026-05-21 20:47:34 App\Jobs\AudioProcessingJob FAIL`
- `2026-05-21 20:48:34 App\Jobs\AudioProcessingJob RUNNING`
- `2026-05-21 20:48:34 App\Jobs\AudioProcessingJob FAIL`
- `2026-05-21 20:53:34 App\Jobs\AudioProcessingJob RUNNING`
- `2026-05-21 20:53:34 App\Jobs\AudioProcessingJob DONE` (job marks DB as FALLIDO on last attempt)

From `minerva-asr` logs during test window:
- Health checks only (`GET /estado`, `GET /estado_cola`)
- **No `POST /upload` observed**

Meaning:
- Failure occurs in Laravel worker **before** request reaches IA upload endpoint.

---

## Connectivity verdict

### What works
- ✅ API auth and resource creation (Asignatura/Tema)
- ✅ Audio enqueue and transcripción record creation
- ✅ Laravel can reach IA health endpoints (`/estado`, `/estado_cola`)

### What fails
- ❌ Queue worker cannot stream uploaded file to IA
- ❌ Pipeline breaks at `AudioProcessingJob` with `Unable to read from stream`
- ❌ IA never receives `/upload`, therefore no callback/sse update possible

Final verdict:
- **Backend ↔ IA control-plane connectivity works (health checks).**
- **Data-plane connectivity for real audio upload is broken at Laravel stream-read stage.**

---

## Recommendations (no code changes applied)

1. **Investigate stream source in `AudioProcessingJob`:**
   - Validate value returned by `Storage::readStream($rutaArchivo)`
   - Log `Storage::exists($rutaArchivo)`, `Storage::size($rutaArchivo)`, and disk path before `Http::attach(...)`

2. **Validate upload persistence path consistency:**
   - Confirm `UploadedFile::store('uploads')` writes file on same disk/container mount visible to `laravel-worker`
   - Confirm file is non-zero bytes at worker execution time

3. **Review multipart request build to IA:**
   - Check `Http::attach(...)->asMultipart()->post(...)` usage in current Laravel version
   - Ensure no malformed multipart payload / invalid stream resource lifecycle

4. **Add integration test for queue upload path:**
   - One test that dispatches `AudioProcessingJob` with real temp file and asserts IA endpoint receives multipart body

5. **Operational guardrail:**
   - On `Unable to read from stream`, persist diagnostic metadata (`rutaArchivo`, disk, file size, readable flag) in transcripción error context

---

## Artifacts generated during audit

- `.tmp-audit/01-seed.log`
- `.tmp-audit/02-token.log`
- `.tmp-audit/03-asignatura.json`
- `.tmp-audit/04-tema.json`
- `.tmp-audit/05-upload.json`
- `.tmp-audit/06-status-poll.log`
- `.tmp-audit/07-worker.log`
- `.tmp-audit/08-asr.log`
- `.tmp-audit/11-final-status.json`
- `.tmp-audit/12-final-log-slice.log`
- `.tmp-audit/16-ia-estado.json`
- `.tmp-audit/17-ia-cola.json`

---

## Conclusion

The requested real E2E test was executed successfully from API side and failed reproducibly in worker stream upload stage. Root symptom is clear and timestamped: `Unable to read from stream` in `AudioProcessingJob` across 3 retries, ending in `FALLIDO`.
