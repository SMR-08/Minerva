import os
import asyncio
import time
import requests
import json
import torch
from fastapi import FastAPI, HTTPException, UploadFile, File, Form
from pydantic import BaseModel
from typing import Optional, Dict, List
import shutil

# Importacion de modulos locales
from ASR.asr import transcribir_audio
# Funciones de post-procesado del módulo compartido
from procesamiento import alinear_transcripcion, suavizar_transcripcion, asignar_hablantes_desconocidos, asignar_roles

# CONFIGURACION
# --- RED ---
URL_DIARIZADOR = os.environ.get("URL_DIARIZADOR", "http://diarizador:8000")
LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://laravel-app:80")
CALLBACK_SECRET = os.environ.get("CALLBACK_SECRET", "cambia_esto_en_produccion")

# --- RUTAS ---
RUTA_TEMPORAL = os.environ.get("RUTA_TEMPORAL", "/tmp")

# --- NOMBRES ARCHIVOS ---
NOMBRE_ARCHIVO_CONVERSACION = os.environ.get("NOMBRE_ARCHIVO_CONVERSACION", "conversacion.json")

# DEFINICION DE LA API
app = FastAPI(
    title="API de Procesamiento de Audio ARS",
    description="Servicio principal de orquestacion para transcripcion (Qwen3-ASR) y diarizacion (Senko).",
    version="2.0-ES"
)

# MODELOS DE DATOS (Pydantic)
class PeticionASR(BaseModel):
    nombre_archivo: str
    idioma: Optional[str] = "auto"
    activar_suavizado: Optional[bool] = True
    activar_correccion_desconocidos: Optional[bool] = True
    activar_asignacion_roles: Optional[bool] = True

# --- MODELOS DE RESPUESTA ---
class MetricasEtapas(BaseModel):
    asr: float
    diarizacion: float
    alineacion: float
    suavizado: float
    correccion_desconocidos: float
    asignacion_roles: float

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
# Procesamiento de peticiones pesadas una a una
procesamiento = asyncio.Lock()
peticiones_en_cola = 0

# Cola de trabajos (para worker.py)
cola_trabajos = asyncio.Queue()

# --- ENDPOINTS ---

@app.get("/estado", response_model=RespuestaEstado)
def verificar_estado():
    """Verifica si el servicio esta activo y si detecta GPU."""
    estado_gpu = torch.cuda.get_device_name(0) if torch.cuda.is_available() else "No disponible"
    return {"estado": "API ARS activa", "gpu": estado_gpu}

@app.post("/upload")
async def upload_audio(
    audio: UploadFile = File(...),
    uuid: str = Form(...),
    idioma: str = Form("auto"),
    callback_url: str = Form(None)
):
    """
    Recibe archivo de audio vía streaming y lo guarda en /tmp para procesamiento.
    Arquitectura "Patata Caliente": el archivo se elimina después de procesar.
    """
    ruta_temporal = os.path.join(RUTA_TEMPORAL, f"{uuid}.wav")

    try:
        # Guardar archivo temporal (streaming chunk-a-chunk)
        with open(ruta_temporal, "wb") as buffer:
            shutil.copyfileobj(audio.file, buffer)

        print(f"[{uuid}] Archivo recibido: {ruta_temporal}")

        # Encolar para procesamiento (si el worker está escuchando)
        await cola_trabajos.put({
            "uuid": uuid,
            "idioma": idioma,
            "callback_url": callback_url
        })

        return {
            "uuid": uuid,
            "estado": "ENCOLADO",
            "mensaje": "Archivo recibido, en cola para procesamiento"
        }

    except Exception as e:
        print(f"[{uuid}] Error al recibir archivo: {e}")
        raise HTTPException(status_code=500, detail=f"Error al recibir archivo: {str(e)}")

@app.get("/estado_cola", response_model=RespuestaCola)
async def obtener_estado_cola():
    """Devuelve si el sistema esta ocupado y cuantos esperan."""
    return {
        "estado": "ocupado" if procesamiento.locked() else "libre",
        "peticiones_en_espera": cola_trabajos.qsize()
    }

def procesar_audio_sincrono(peticion: PeticionASR) -> dict:
    """
    Ejecuta el flujo completo (ASR -> Diarizacion -> Alineacion) de forma sincrona.
    Se ejecuta en un hilo aparte.
    """
    metricas = {}
    inicio_total = time.time()
    
    try:
        # 1. Ejecutar ASR (Local)
        print(f"1. Ejecutando ASR para {peticion.nombre_archivo}...")
        inicio_asr = time.time()
        
        # Validacion simple de idioma
        arg_idioma = None
        if peticion.idioma and peticion.idioma.lower() != "auto":
            arg_idioma = peticion.idioma.lower()
            
        resultado_asr = transcribir_audio(
            nombre_archivo=peticion.nombre_archivo,
            idioma=arg_idioma
        )
        metricas["asr"] = time.time() - inicio_asr
        
        # Obtener datos en memoria (claves en español tras refactor de asr.py)
        datos_asr = resultado_asr["datos"]
        lista_palabras = datos_asr["palabras"] # [{palabra, inicio, fin, probabilidad}]
        duracion_audio = resultado_asr["duracion"]
        
        uuid_asr = os.path.basename(os.path.dirname(resultado_asr["ruta_texto"]))

        # 2. Ejecutar Diarizacion (Servicio Remoto)
        print(f"2. Solicitando Diarizacion a {URL_DIARIZADOR}...")
        inicio_diar = time.time()
        try:
            # Enviamos solo el nombre del archivo, el servicio diarizador ya sabe que esta en /app/audios/entrada
            # Ojo: DIARIZADOR/main.py espera {"ruta_archivo": "nombre.wav"}
            
            resp = requests.post(
                f"{URL_DIARIZADOR}/diarizar",
                json={"ruta_archivo": f"entrada/{peticion.nombre_archivo}"} 
                # NOTA: En Diarizador, ruta_absoluta = /app/audios/{nombre_archivo}.
                # Si enviamos "entrada/audio.wav", quedara /app/audios/entrada/audio.wav -> CORRECTO.
            )
            resp.raise_for_status()
            resultado_diar = resp.json()
            segmentos = resultado_diar["segmentos"] # [{inicio, fin, hablante}]
            
        except Exception as e:
            print(f"Error llamando al diarizador: {e}")
            raise HTTPException(status_code=502, detail=f"Fallo servicio diarizacion: {e}")
        metricas["diarizacion"] = time.time() - inicio_diar

        # --- DEBUG DUMP ---
        try:
            with open(f"/app/transcripciones/debug_palabras_{uuid_asr}.json", "w") as f:
                json.dump(lista_palabras, f)
            with open(f"/app/transcripciones/debug_segmentos_{uuid_asr}.json", "w") as f:
                json.dump(segmentos, f)
        except Exception as e:
            print(f"Error guardando debug: {e}")
            
        # 3. Alineacion
        print("3. Alineando resultados...")
        inicio_alineacion = time.time()
        salida_alineada = alinear_transcripcion(lista_palabras, segmentos)
        metricas["alineacion"] = time.time() - inicio_alineacion
        
        # 4. Suavizado
        if peticion.activar_suavizado:
            print("4. Suavizando (Zero Gap)...")
            inicio_suave = time.time()
            salida_suavizada = suavizar_transcripcion(salida_alineada)
            metricas["suavizado"] = time.time() - inicio_suave
        else:
            salida_suavizada = salida_alineada
            metricas["suavizado"] = 0.0
            
        # 5. Correccion Desconocidos
        if peticion.activar_correccion_desconocidos:
            print("5. Corrigiendo interlocutores desconocidos...")
            inicio_desc = time.time()
            salida_corregida = asignar_hablantes_desconocidos(salida_suavizada)
            metricas["correccion_desconocidos"] = time.time() - inicio_desc
        else:
            salida_corregida = salida_suavizada
            metricas["correccion_desconocidos"] = 0.0
            
        # 6. Asignacion de Roles
        if peticion.activar_asignacion_roles:
            print("6. Asignando roles (Profesor/Alumnos)...")
            inicio_roles = time.time()
            salida_final = asignar_roles(salida_corregida)
            metricas["asignacion_roles"] = time.time() - inicio_roles
        else:
            salida_final = salida_corregida
            metricas["asignacion_roles"] = 0.0
            
        # Calculo final metricas
        tiempo_total = time.time() - inicio_total
        rtf = (tiempo_total / duracion_audio * 100) if duracion_audio > 0 else 0
        
        resumen_metricas = {
            "tiempo_procesamiento_total_segundos": round(tiempo_total, 2),
            "duracion_audio_segundos": round(duracion_audio, 2),
            "factor_tiempo_real_porcentaje": round(rtf, 2),
            "tiempos_etapa_segundos": {k: max(round(v, 4), 0.0001) for k, v in metricas.items()}
        }
        
        # 7. Guardar Resultado Final
        dir_salida = os.path.dirname(resultado_asr["ruta_texto"])
        ruta_conversacion = os.path.join(dir_salida, NOMBRE_ARCHIVO_CONVERSACION)
        
        payload_resultado = {
            "estado": "exito",
            "uuid_asr": uuid_asr,
            "metricas_rendimiento": resumen_metricas,
            "transcripcion": salida_final
        }
        
        with open(ruta_conversacion, "w", encoding="utf-8") as f:
            json.dump(payload_resultado, f, ensure_ascii=False, indent=4)
            
        return payload_resultado

    except HTTPException as he:
        raise he
    except Exception as e:
        import traceback
        traceback.print_exc()
        raise HTTPException(status_code=500, detail=f"Error Interno Servidor: {str(e)}")

@app.post("/transcribir_diarizado", response_model=RespuestaProcesamiento)
async def endpoint_transcribir_diarizado(peticion: PeticionASR):
    """
    Orquestador principal. Ejecuta ASR y Diarizacion de forma secuencial
    usando una cola de bloqueo para no saturar la GPU.
    """
    global peticiones_en_cola
    
    peticiones_en_cola += 1
    posicion = peticiones_en_cola
    print(f"Peticion en cola. Posicion: {posicion}. Esperando...")
    
    try:
        async with procesamiento:
            peticiones_en_cola -= 1
            print(f"Procesando {peticion.nombre_archivo}. Restantes en cola: {peticiones_en_cola}")
            
            # Ejecutar logica pesada en hilo aparte para no bloquear API
            resultado = await asyncio.to_thread(procesar_audio_sincrono, peticion)
            return resultado
            
    except Exception as e:
        print(f"Error en cola: {e}")
        raise e

# PUNTO DE ENTRADA
if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)