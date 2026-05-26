"""
Minerva IA — Worker de Cola Unificada

Arquitectura:
  - Consume tareas de Redis (BRPOP minerva_tasks)
  - Procesa en orden FIFO (transcripción, resumen)
  - Semáforo GPU configurable (GPU_CONCURRENCY)
  - GPUModelManager para load/unload en modo compact
  - Callback HTTP a Laravel con resultados
  - FastAPI para healthcheck y estado

Configuración via .env:
  REDIS_URL, GPU_MODE, GPU_CONCURRENCY, CPU_CONCURRENCY,
  AUTO_SUMMARY, SUMMARY_MODEL, DISPOSITIVO_ASR, etc.
"""

import os
import asyncio
import time
import json
import aiohttp
import torch
from contextlib import asynccontextmanager
from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import Optional

from ASR.asr import transcribir_audio
from procesamiento import (
    alinear_transcripcion,
    suavizar_transcripcion,
    asignar_hablantes_desconocidos,
    asignar_roles,
)
from gpu_manager import GPUModelManager
import logger as app_logger

# ==============================================================================
# CONFIGURACIÓN
# ==============================================================================

REDIS_URL = os.environ.get("REDIS_URL", "redis://:minerva_dev_redis@minerva-redis:6379/0")
LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://minerva-nginx:80")
CALLBACK_SECRET = os.environ.get("IA_CALLBACK_SECRET", "cambiar")
URL_DIARIZADOR = os.environ.get("URL_DIARIZADOR", "http://minerva-diarizador:8000")
RUTA_TEMPORAL = os.environ.get("RUTA_TEMPORAL", "/tmp")

GPU_MODE = os.environ.get("GPU_MODE", "compact")
GPU_CONCURRENCY = int(os.environ.get("GPU_CONCURRENCY", "1"))
CPU_CONCURRENCY = int(os.environ.get("CPU_CONCURRENCY", "2"))
AUTO_SUMMARY = os.environ.get("AUTO_SUMMARY", "true").lower() == "true"
QUEUE_KEY = os.environ.get("REDIS_QUEUE_KEY", "minerva_tasks")
STATUS_PREFIX = os.environ.get("REDIS_STATUS_PREFIX", "minerva_status")

log = app_logger.get_logger(__name__)

# ==============================================================================
# ESTADO GLOBAL
# ==============================================================================

gpu_manager = GPUModelManager()
semaforo_gpu = asyncio.Semaphore(GPU_CONCURRENCY)
redis_client = None  # Se inicializa en lifespan
worker_task = None
stats = {"processed": 0, "failed": 0, "current_task": None}


# ==============================================================================
# REDIS
# ==============================================================================

async def get_redis():
    import redis.asyncio as aioredis
    return aioredis.from_url(REDIS_URL, decode_responses=True)


async def update_status(uuid: str, **kwargs):
    """Actualiza estado de una tarea en Redis (visible por Laravel)."""
    if redis_client:
        await redis_client.hset(f"{STATUS_PREFIX}:{uuid}", mapping={
            k: str(v) for k, v in kwargs.items()
        })


# ==============================================================================
# CALLBACKS A LARAVEL
# ==============================================================================

async def notificar_laravel(callback_url: str, uuid: str, estado: str,
                            resultado: dict = None, error: str = None,
                            resumen: str = None):
    """Envía resultado a Laravel via callback HTTP."""
    payload = {"uuid": uuid, "estado": estado}
    if resultado:
        payload["resultado"] = resultado
    if error:
        payload["error"] = error
    if resumen:
        payload["resumen"] = resumen

    url = f"{LARAVEL_URL}/api/ia/callback"
    headers = {
        "X-Callback-Secret": CALLBACK_SECRET,
        "Content-Type": "application/json",
        "Accept": "application/json",
    }

    for intento in range(1, 4):
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(url, json=payload, headers=headers,
                                        timeout=aiohttp.ClientTimeout(total=30)) as resp:
                    if resp.status == 200:
                        log.info("Callback enviado", trace_id=uuid, estado=estado)
                        return
                    log.warning("Callback error", trace_id=uuid, status=resp.status,
                                intento=intento)
        except Exception as e:
            log.error("Callback excepción", trace_id=uuid, error=str(e), intento=intento)
        await asyncio.sleep(2 ** intento)


# ==============================================================================
# DESCARGA DE AUDIO (patata caliente invertida)
# ==============================================================================

async def descargar_audio(tarea: dict) -> str:
    """Descarga el audio de Laravel cuando le toca en la cola."""
    uuid = tarea["uuid"]
    audio_url = tarea["audio_url"]
    ruta_local = os.path.join(RUTA_TEMPORAL, f"{uuid}.wav")

    async with aiohttp.ClientSession() as session:
        async with session.get(
            audio_url,
            headers={"Authorization": f"Bearer {CALLBACK_SECRET}"},
            timeout=aiohttp.ClientTimeout(total=300),
        ) as response:
            if response.status != 200:
                raise Exception(f"Error descargando audio: HTTP {response.status}")

            with open(ruta_local, "wb") as f:
                async for chunk in response.content.iter_chunked(1024 * 1024):
                    f.write(chunk)

    size_mb = round(os.path.getsize(ruta_local) / 1048576, 2)
    log.info("Audio descargado", trace_id=uuid, size_mb=size_mb)
    return ruta_local


# ==============================================================================
# PROCESAMIENTO DE TAREAS
# ==============================================================================

async def procesar_transcripcion(tarea: dict):
    """Pipeline completo: descarga → ASR → diarización → postprocesado → callback."""
    uuid = tarea["uuid"]
    idioma = tarea.get("idioma", "auto")
    metricas = {}
    inicio_total = time.time()

    # Notificar a Laravel que empezamos a procesar
    await notificar_laravel(tarea.get("callback_url", ""), uuid, "PROCESANDO")

    try:
        # 1. Descargar audio
        await update_status(uuid, estado="PROCESANDO", progreso=5, etapa="DESCARGA")
        ruta_audio = await descargar_audio(tarea)
        # 2. ASR
        await update_status(uuid, estado="PROCESANDO", progreso=10, etapa="ASR")
        modelo_asr = await gpu_manager.ensure_loaded("asr")

        inicio_asr = time.time()
        arg_idioma = None if (not idioma or idioma.lower() == "auto") else idioma.lower()
        resultado_asr = await asyncio.to_thread(transcribir_audio, ruta_audio, arg_idioma)
        metricas["asr"] = time.time() - inicio_asr

        datos_asr = resultado_asr["datos"]
        lista_palabras = datos_asr["palabras"]
        duracion_audio = resultado_asr["duracion"]

        await update_status(uuid, estado="PROCESANDO", progreso=40, etapa="ASR")

        # 3. Diarización (delegada al microservicio)
        await update_status(uuid, estado="PROCESANDO", progreso=50, etapa="DIARIZACION")

        inicio_diar = time.time()
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{URL_DIARIZADOR}/diarizar",
                json={"ruta_archivo": os.path.basename(ruta_audio)},
                timeout=aiohttp.ClientTimeout(total=600),
            ) as resp:
                if resp.status != 200:
                    raise Exception(f"Diarizador retornó {resp.status}")
                resultado_diar = await resp.json()
                segmentos = resultado_diar.get("segmentos", [])
        metricas["diarizacion"] = time.time() - inicio_diar

        await update_status(uuid, estado="PROCESANDO", progreso=70, etapa="POSTPROCESADO")

        # 4. Post-procesamiento (CPU)
        inicio_post = time.time()
        salida = await asyncio.to_thread(alinear_transcripcion, lista_palabras, segmentos)
        salida = await asyncio.to_thread(suavizar_transcripcion, salida)
        salida = await asyncio.to_thread(asignar_hablantes_desconocidos, salida)
        salida = await asyncio.to_thread(asignar_roles, salida)
        metricas["postprocesado"] = time.time() - inicio_post

        # 5. Métricas
        tiempo_total = time.time() - inicio_total
        rtf = (tiempo_total / duracion_audio * 100) if duracion_audio > 0 else 0

        resultado = {
            "estado": "exito",
            "uuid_asr": uuid,
            "metricas_rendimiento": {
                "tiempo_procesamiento_total_segundos": round(tiempo_total, 2),
                "duracion_audio_segundos": round(duracion_audio, 2),
                "factor_tiempo_real_porcentaje": round(rtf, 2),
                "tiempos_etapa_segundos": {k: round(v, 4) for k, v in metricas.items()},
            },
            "transcripcion": salida,
        }

        # 6. Callback
        await update_status(uuid, estado="COMPLETADO", progreso=100)
        await notificar_laravel(tarea.get("callback_url", ""), uuid, "COMPLETADO",
                                resultado=resultado)

        log.info("Transcripción completada", trace_id=uuid,
                 duracion_s=round(tiempo_total, 1))

        # 7. Auto-encolar resumen
        if AUTO_SUMMARY and redis_client:
            texto_plano = " ".join([s.get("texto", "") for s in salida]) if isinstance(salida, list) else str(salida)
            await redis_client.rpush(QUEUE_KEY, json.dumps({
                "type": "summary",
                "uuid": uuid,
                "texto": texto_plano,
                "callback_url": tarea.get("callback_url", ""),
                "created_at": time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime()),
            }))
            log.info("Resumen auto-encolado", trace_id=uuid)

    finally:
        # Limpiar archivo temporal
        if os.path.exists(ruta_audio):
            os.remove(ruta_audio)


URL_RESUMIDOR = os.environ.get("URL_RESUMIDOR", "http://minerva-resumidor:8000")

SUMMARY_SYSTEM_PROMPT = """Eres un asistente que resume clases universitarias.
Genera un resumen estructurado en markdown del texto proporcionado.
El resumen debe incluir:
- Tema principal
- Puntos clave (lista con viñetas)
- Preguntas de alumnos (si las hay)
Sé conciso. Máximo 500 palabras.
Responde en el mismo idioma que el texto. Si no puedes detectarlo, usa español."""


async def procesar_resumen(tarea: dict):
    """Genera resumen: modo full delega a microservicio, compact genera local."""
    uuid = tarea["uuid"]
    texto = tarea.get("texto", "")

    if not texto or len(texto.strip()) < 50:
        log.warning("Texto demasiado corto para resumir", trace_id=uuid)
        await notificar_laravel(tarea.get("callback_url", ""), uuid, "LISTO",
                                resumen="Texto demasiado corto para generar resumen.")
        await update_status(uuid, estado="LISTO", progreso=100)
        return

    await update_status(uuid, estado="RESUMIENDO", progreso=0)
    await notificar_laravel(tarea.get("callback_url", ""), uuid, "RESUMIENDO")

    try:
        if GPU_MODE == "full":
            resumen = await _resumir_via_microservicio(texto, tarea.get("idioma", "es"))
        else:
            resumen = await _resumir_local(texto, tarea.get("idioma", "es"))
    except Exception as e:
        log.error("Error generando resumen", trace_id=uuid, error=str(e))
        resumen = f"Error al generar resumen: {str(e)}"

    await update_status(uuid, estado="LISTO", progreso=100)
    await notificar_laravel(tarea.get("callback_url", ""), uuid, "LISTO", resumen=resumen)
    log.info("Resumen completado", trace_id=uuid, length=len(resumen))


async def _resumir_via_microservicio(texto: str, idioma: str) -> str:
    """Modo full: POST al microservicio minerva-resumidor."""
    async with aiohttp.ClientSession() as session:
        async with session.post(
            f"{URL_RESUMIDOR}/resumir",
            json={
                "texto": texto,
                "idioma": idioma,
                "max_tokens": int(os.environ.get("SUMMARY_MAX_TOKENS", 1024)),
            },
            timeout=aiohttp.ClientTimeout(total=120),
        ) as resp:
            if resp.status != 200:
                body = await resp.text()
                raise Exception(f"Resumidor HTTP {resp.status}: {body[:200]}")
            resultado = await resp.json()
            return resultado.get("resumen", "")


async def _resumir_local(texto: str, idioma: str) -> str:
    """Modo compact: carga modelo via GPUModelManager, genera localmente."""
    modelo_data = await gpu_manager.ensure_loaded("summary")
    model = modelo_data["model"]
    tokenizer = modelo_data["tokenizer"]

    max_input = int(os.environ.get("SUMMARY_INPUT_MAX_TOKENS", 32768))
    tokens = tokenizer.encode(texto)
    if len(tokens) > max_input:
        texto = tokenizer.decode(tokens[:max_input])

    messages = [
        {"role": "system", "content": SUMMARY_SYSTEM_PROMPT},
        {"role": "user", "content": f"Resume la siguiente transcripción de clase:\n\n{texto}"},
    ]

    input_text = tokenizer.apply_chat_template(messages, tokenize=False, add_generation_prompt=True)
    inputs = tokenizer(input_text, return_tensors="pt").to(model.device)

    import torch as _torch
    with _torch.no_grad():
        outputs = await asyncio.to_thread(
            model.generate,
            **inputs,
            max_new_tokens=int(os.environ.get("SUMMARY_MAX_TOKENS", 1024)),
            temperature=1.0,
            top_p=1.0,
            top_k=20,
            do_sample=True,
        )

    resumen = tokenizer.decode(outputs[0][inputs["input_ids"].shape[1]:], skip_special_tokens=True)

    if len(resumen.strip()) < 20:
        resumen = "No se pudo generar un resumen adecuado."

    return resumen


# ==============================================================================
# WORKER LOOP (consume de Redis)
# ==============================================================================

async def worker_loop():
    """Loop principal: BRPOP de Redis, procesa tareas FIFO."""
    global redis_client
    redis_client = await get_redis()

    log.info("Worker iniciado", redis_url=REDIS_URL.split("@")[-1],
             gpu_mode=GPU_MODE, gpu_concurrency=GPU_CONCURRENCY)

    # Precargar modelos si modo full
    if GPU_MODE == "full":
        await gpu_manager.preload_all()

    while True:
        try:
            result = await redis_client.brpop(QUEUE_KEY, timeout=0)
            if result is None:
                continue

            _, raw = result
            tarea = json.loads(raw)
            uuid = tarea.get("uuid", "unknown")
            task_type = tarea.get("type", "unknown")

            log.info("Tarea recibida", trace_id=uuid, type=task_type)
            stats["current_task"] = uuid

            async with semaforo_gpu:
                if task_type == "transcription":
                    await procesar_transcripcion(tarea)
                elif task_type == "summary":
                    await procesar_resumen(tarea)
                else:
                    log.warning("Tipo de tarea desconocido", type=task_type)

            stats["processed"] += 1
            stats["current_task"] = None

        except Exception as e:
            stats["failed"] += 1
            stats["current_task"] = None
            log.error("Error en worker loop", error=str(e))
            # Notificar FALLIDO a Laravel y Redis
            if uuid and uuid != "unknown":
                await update_status(uuid, estado="FALLIDO", progreso=0)
                await notificar_laravel(tarea.get("callback_url", ""), uuid, "FALLIDO", error=str(e))
            await asyncio.sleep(5)


# ==============================================================================
# LOADER DE MODELO RESUMEN (para GPUModelManager en modo compact)
# ==============================================================================

def _cargar_modelo_resumen():
    """Carga Qwen3.5-0.8B para resumen. Retorna dict con model+tokenizer."""
    from transformers import AutoModelForCausalLM, AutoTokenizer
    model_name = os.environ.get("SUMMARY_MODEL", "Qwen/Qwen3.5-0.8B")
    device = os.environ.get("DISPOSITIVO_ASR", "cuda:0")

    tokenizer = AutoTokenizer.from_pretrained(model_name, trust_remote_code=True)
    model = AutoModelForCausalLM.from_pretrained(
        model_name,
        torch_dtype=torch.bfloat16,
        device_map=device,
        trust_remote_code=True,
    )
    model.eval()
    return {"model": model, "tokenizer": tokenizer}


# ==============================================================================
# FASTAPI (healthcheck + estado)
# ==============================================================================

@asynccontextmanager
async def lifespan(app):
    global worker_task
    # Registrar loaders de modelos
    from ASR.asr import cargar_modelo as cargar_asr
    gpu_manager.register_loader("asr", cargar_asr)
    # Loader de resumen solo si NO hay microservicio (modo compact sin resumidor externo)
    if GPU_MODE != "full" or not os.environ.get("URL_RESUMIDOR"):
        gpu_manager.register_loader("summary", _cargar_modelo_resumen)
    # TODO: registrar loader de diarización cuando se gestione localmente
    # gpu_manager.register_loader("diarizacion", cargar_diarizacion)

    # Iniciar worker en background
    worker_task = asyncio.create_task(worker_loop())
    log.info("Worker task creada")
    yield
    # Shutdown
    if worker_task:
        worker_task.cancel()


app = FastAPI(title="Minerva IA — Cola Unificada", lifespan=lifespan)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class RespuestaEstado(BaseModel):
    estado: str
    gpu: str
    gpu_mode: str
    worker: str
    processed: int
    failed: int
    current_task: Optional[str]


class RespuestaCola(BaseModel):
    queue_length: int
    gpu_status: dict


@app.get("/estado", response_model=RespuestaEstado)
async def verificar_estado():
    gpu_name = torch.cuda.get_device_name(0) if torch.cuda.is_available() else "No disponible"
    return {
        "estado": "Worker activo",
        "gpu": gpu_name,
        "gpu_mode": GPU_MODE,
        "worker": "running" if worker_task and not worker_task.done() else "stopped",
        "processed": stats["processed"],
        "failed": stats["failed"],
        "current_task": stats["current_task"],
    }


@app.get("/estado_cola", response_model=RespuestaCola)
async def obtener_estado_cola():
    queue_len = 0
    if redis_client:
        queue_len = await redis_client.llen(QUEUE_KEY)
    return {
        "queue_length": queue_len,
        "gpu_status": gpu_manager.get_status(),
    }


# ==============================================================================
# PUNTO DE ENTRADA
# ==============================================================================

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
