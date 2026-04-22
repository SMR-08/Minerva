import os
import asyncio
import time
import aiohttp
import json
import torch
from contextlib import asynccontextmanager
from fastapi import FastAPI, HTTPException, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, Dict, List
import shutil

# Importacion de modulos locales
from ASR.asr import transcribir_audio
from procesamiento import alinear_transcripcion, suavizar_transcripcion, asignar_hablantes_desconocidos, asignar_roles

# CONFIGURACION
URL_DIARIZADOR = os.environ.get("URL_DIARIZADOR", "http://diarizador:8000")
LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://laravel-app:80")
CALLBACK_SECRET = os.environ.get("CALLBACK_SECRET", "cambia_esto_en_produccion")
RUTA_TEMPORAL = os.environ.get("RUTA_TEMPORAL", "/tmp")
NOMBRE_ARCHIVO_CONVERSACION = os.environ.get("NOMBRE_ARCHIVO_CONVERSACION", "conversacion.json")

# MODELOS DE DATOS
class PeticionASR(BaseModel):
    nombre_archivo: str
    idioma: Optional[str] = "auto"
    activar_suavizado: Optional[bool] = True
    activar_correccion_desconocidos: Optional[bool] = True
    activar_asignacion_roles: Optional[bool] = True

class MetricasRendimiento(BaseModel):
    tiempo_procesamiento_total_segundos: float
    duracion_audio_segundos: float
    factor_tiempo_real_porcentaje: float
    tiempos_etapa_segundos: Dict[str, float]

class ItemTranscripcion(BaseModel):
    hablante: str
    inicio: float
    fin: float
    texto: str

class RespuestaProcesamiento(BaseModel):
    estado: str
    uuid_asr: str
    metricas_rendimiento: MetricasRendimiento
    transcripcion: List[ItemTranscripcion]

class RespuestaEstado(BaseModel):
    estado: str
    gpu: str

class RespuestaCola(BaseModel):
    estado: str
    peticiones_en_espera: int

# GESTION DE CONCURRENCIA
procesamiento = asyncio.Lock()
cola_trabajos = asyncio.Queue()


# --- WORKER ---

async def notificar_a_laravel(uuid: str, estado: str, resultado: dict = None, error: str = None, callback_url: str = None):
    payload = {"uuid": uuid, "estado": estado}
    if estado == "COMPLETADO" and resultado:
        payload["resultado"] = resultado
    elif estado == "FALLIDO" and error:
        payload["error"] = error

    url = callback_url or f"{LARAVEL_URL}/api/ia/callback"
    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                url,
                json=payload,
                headers={
                    "Authorization": f"Bearer {CALLBACK_SECRET}",
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                timeout=aiohttp.ClientTimeout(total=30)
            ) as response:
                print(f"[{uuid}] Callback → {url} : {response.status}", flush=True)
    except Exception as e:
        print(f"[{uuid}] Error callback: {e}", flush=True)


async def actualizar_progreso_laravel(uuid: str, estado: str, progreso: int = None, etapa: str = None, callback_url: str = None):
    """Actualiza el progreso en Laravel vía SSE update."""
    payload = {"uuid": uuid, "estado": estado}
    if progreso is not None:
        payload["progreso"] = progreso
    if etapa is not None:
        payload["etapa"] = etapa

    base = LARAVEL_URL.rstrip('/')

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{base}/api/ia/sse-update",
                json=payload,
                headers={
                    "Authorization": f"Bearer {CALLBACK_SECRET}",
                    "Content-Type": "application/json",
                    "Accept": "application/json"
                },
                timeout=aiohttp.ClientTimeout(total=10)
            ) as response:
                if response.status != 200:
                    print(f"[{uuid}] SSE update error: {response.status}", flush=True)
    except Exception as e:
        print(f"[{uuid}] SSE update excepcion: {e}", flush=True)


async def procesar_tarea(uuid: str, idioma: str, callback_url: str = None):
    ruta_archivo = os.path.join(RUTA_TEMPORAL, f"{uuid}.wav")
    metricas = {}
    inicio_total = time.time()

    if not os.path.exists(ruta_archivo):
        error_msg = f"Archivo no encontrado: {ruta_archivo}"
        print(f"[{uuid}] ERROR: {error_msg}", flush=True)
        await notificar_a_laravel(uuid, "FALLIDO", error=error_msg, callback_url=callback_url)
        return

    try:
        print(f"[{uuid}] Iniciando procesamiento...", flush=True)

        # Etapa 1: ASR
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=10, etapa="ASR", callback_url=callback_url)
        print(f"[{uuid}] 1. ASR...", flush=True)
        inicio_asr = time.time()
        arg_idioma = None if not idioma or idioma.lower() == "auto" else idioma.lower()
        resultado_asr = await asyncio.to_thread(transcribir_audio, nombre_archivo=ruta_archivo, idioma=arg_idioma)
        metricas["asr"] = time.time() - inicio_asr
        lista_palabras = resultado_asr["datos"]["palabras"]
        duracion_audio = resultado_asr["duracion"]
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=40, etapa="ASR", callback_url=callback_url)

        # Etapa 2: Diarización
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=50, etapa="DIARIZACION", callback_url=callback_url)
        print(f"[{uuid}] 2. Diarizacion...", flush=True)
        inicio_diar = time.time()

        # Copiar archivo al directorio compartido para que el diarizador pueda accederlo
        ruta_entrada = os.environ.get("RUTA_ENTRADA", "/app/compartido/entrada")
        nombre_compartido = f"{uuid}.wav"
        ruta_compartida = os.path.join(ruta_entrada, nombre_compartido)
        shutil.copy2(ruta_archivo, ruta_compartida)

        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    f"{URL_DIARIZADOR}/diarizar",
                    json={"ruta_archivo": nombre_compartido},
                    timeout=aiohttp.ClientTimeout(total=600)
                ) as response:
                    if response.status == 200:
                        resultado_diar = await response.json()
                        segmentos = resultado_diar.get("segmentos", [])
                    else:
                        raise Exception(f"Diarizador retorno {response.status}")
        finally:
            # Limpiar archivo compartido
            if os.path.exists(ruta_compartida):
                os.remove(ruta_compartida)

        metricas["diarizacion"] = time.time() - inicio_diar
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=70, etapa="DIARIZACION", callback_url=callback_url)

        # Etapa 3: Alineacion
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=75, etapa="ALINEACION", callback_url=callback_url)
        print(f"[{uuid}] 3. Alineacion...", flush=True)
        inicio_alineacion = time.time()
        salida_alineada = await asyncio.to_thread(alinear_transcripcion, lista_palabras, segmentos)
        metricas["alineacion"] = time.time() - inicio_alineacion

        # Etapa 4: Suavizado
        print(f"[{uuid}] 4. Suavizado...", flush=True)
        inicio_suave = time.time()
        salida_suavizada = await asyncio.to_thread(suavizar_transcripcion, salida_alineada)
        metricas["suavizado"] = time.time() - inicio_suave

        # Etapa 5: Correccion desconocidos
        print(f"[{uuid}] 5. Correccion desconocidos...", flush=True)
        inicio_desc = time.time()
        salida_corregida = await asyncio.to_thread(asignar_hablantes_desconocidos, salida_suavizada)
        metricas["correccion_desconocidos"] = time.time() - inicio_desc

        # Etapa 6: Roles
        print(f"[{uuid}] 6. Asignacion roles...", flush=True)
        inicio_roles = time.time()
        salida_final = await asyncio.to_thread(asignar_roles, salida_corregida)
        metricas["asignacion_roles"] = time.time() - inicio_roles

        tiempo_total = time.time() - inicio_total
        rtf = (tiempo_total / duracion_audio * 100) if duracion_audio > 0 else 0

        resumen_metricas = {
            "tiempo_procesamiento_total_segundos": round(tiempo_total, 2),
            "duracion_audio_segundos": round(duracion_audio, 2),
            "factor_tiempo_real_porcentaje": round(rtf, 2),
            "tiempos_etapa_segundos": {k: max(round(v, 4), 0.0001) for k, v in metricas.items()}
        }

        payload_resultado = {
            "estado": "exito",
            "uuid_asr": uuid,
            "metricas_rendimiento": resumen_metricas,
            "transcripcion": salida_final
        }

        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=100, etapa="POST_PROCESADO", callback_url=callback_url)
        await notificar_a_laravel(uuid, "COMPLETADO", resultado=payload_resultado, callback_url=callback_url)
        print(f"[{uuid}] Completado.", flush=True)

    except Exception as e:
        import traceback
        traceback.print_exc()
        error_msg = f"Error interno: {str(e)}"
        print(f"[{uuid}] ERROR: {error_msg}", flush=True)
        await notificar_a_laravel(uuid, "FALLIDO", error=error_msg, callback_url=callback_url)

    finally:
        if os.path.exists(ruta_archivo):
            os.remove(ruta_archivo)
            print(f"[{uuid}] Archivo temporal eliminado.", flush=True)


async def worker_loop():
    print("=" * 60, flush=True)
    print("WORKER INTEGRADO INICIADO", flush=True)
    print(f"URL Diarizador: {URL_DIARIZADOR}", flush=True)
    print(f"URL Laravel: {LARAVEL_URL}", flush=True)
    print("=" * 60, flush=True)

    while True:
        try:
            tarea = await cola_trabajos.get()
            uuid = tarea["uuid"]
            idioma = tarea.get("idioma", "auto")
            callback_url = tarea.get("callback_url")
            print(f"\n[{uuid}] Procesando (idioma: {idioma})...", flush=True)
            async with procesamiento:
                await procesar_tarea(uuid, idioma, callback_url)
            cola_trabajos.task_done()
        except Exception as e:
            print(f"Error en worker loop: {e}", flush=True)
            await asyncio.sleep(5)


@asynccontextmanager
async def lifespan(app: FastAPI):
    task = asyncio.create_task(worker_loop())
    print("Worker arrancado.", flush=True)
    yield
    task.cancel()


# DEFINICION DE LA API
app = FastAPI(
    title="API de Procesamiento de Audio ARS",
    description="Servicio principal de orquestacion para transcripcion (Qwen3-ASR) y diarizacion (Senko).",
    version="2.0-ES",
    lifespan=lifespan
)


# --- ENDPOINTS ---

@app.get("/estado", response_model=RespuestaEstado)
def verificar_estado():
    estado_gpu = torch.cuda.get_device_name(0) if torch.cuda.is_available() else "No disponible"
    return {"estado": "API ARS activa", "gpu": estado_gpu}


@app.post("/upload")
async def upload_audio(
    audio: UploadFile = File(...),
    uuid: str = Form(...),
    idioma: str = Form("auto"),
    callback_url: str = Form(None)
):
    ruta_temporal = os.path.join(RUTA_TEMPORAL, f"{uuid}.wav")
    try:
        with open(ruta_temporal, "wb") as buffer:
            shutil.copyfileobj(audio.file, buffer)
        print(f"[{uuid}] Archivo recibido: {ruta_temporal}", flush=True)
        print(f"[{uuid}] callback_url recibido: {callback_url}", flush=True)
        await cola_trabajos.put({"uuid": uuid, "idioma": idioma, "callback_url": callback_url})
        return {"uuid": uuid, "estado": "ENCOLADO", "mensaje": "Archivo recibido, en cola para procesamiento"}
    except Exception as e:
        print(f"[{uuid}] Error al recibir archivo: {e}", flush=True)
        raise HTTPException(status_code=500, detail=f"Error al recibir archivo: {str(e)}")


@app.get("/estado_cola", response_model=RespuestaCola)
async def obtener_estado_cola():
    return {
        "estado": "ocupado" if procesamiento.locked() else "libre",
        "peticiones_en_espera": cola_trabajos.qsize()
    }


# PUNTO DE ENTRADA
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)
