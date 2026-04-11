"""
Worker de Procesamiento de Audio - Arquitectura "Patata Caliente"

Este worker escucha una cola de archivos por procesar y ejecuta:
1. ASR (transcripción)
2. Diarización
3. Alineación y post-procesado
4. Notificación a Laravel vía callback
5. Limpieza del archivo temporal

El archivo de audio NUNCA se guarda permanentemente.
"""

import os
import asyncio
import aiohttp
import json
import time
import torch
from typing import Optional, Dict

# Importación de módulos locales
from ASR.asr import transcribir_audio
# Las funciones de post-procesado están en el módulo compartido
from procesamiento import alinear_transcripcion, suavizar_transcripcion, asignar_hablantes_desconocidos, asignar_roles

# ==============================================================================
# CONFIGURACIÓN
# ==============================================================================

URL_DIARIZADOR = os.environ.get("URL_DIARIZADOR", "http://minerva-diarizador:8000")
LARAVEL_URL = os.environ.get("LARAVEL_URL", "http://laravel-app:80")
CALLBACK_SECRET = os.environ.get("CALLBACK_SECRET", "cambia_esto_en_produccion")
RUTA_TEMPORAL = os.environ.get("RUTA_TEMPORAL", "/tmp")

# Cola de procesamiento (en memoria para este worker)
cola_procesamiento = asyncio.Queue()
procesando_actual = None  # UUID del archivo en procesamiento

# ==============================================================================
# LÓGICA DE PROCESAMIENTO
# ==============================================================================

async def notificar_a_laravel(uuid: str, estado: str, resultado: dict = None, error: str = None):
    """Envía el resultado del procesamiento a Laravel vía callback."""
    payload = {
        "uuid": uuid,
        "estado": estado,
    }

    if estado == "COMPLETADO" and resultado:
        payload["resultado"] = resultado
    elif estado == "FALLIDO" and error:
        payload["error"] = error

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{LARAVEL_URL}/api/ia/callback",
                json=payload,
                headers={
                    "Authorization": f"Bearer {CALLBACK_SECRET}",
                    "Content-Type": "application/json"
                },
                timeout=aiohttp.ClientTimeout(total=30)
            ) as response:
                if response.status == 200:
                    print(f"[{uuid}] Callback enviado exitosamente a Laravel")
                else:
                    print(f"[{uuid}] Error al enviar callback: {response.status}")
    except Exception as e:
        print(f"[{uuid}] Excepción al enviar callback: {e}")


async def actualizar_progreso_laravel(uuid: str, estado: str, progreso: int = None, etapa: str = None):
    """Actualiza el progreso en Laravel vía SSE update."""
    payload = {
        "uuid": uuid,
        "estado": estado,
    }

    if progreso is not None:
        payload["progreso"] = progreso
    if etapa is not None:
        payload["etapa"] = etapa

    try:
        async with aiohttp.ClientSession() as session:
            async with session.post(
                f"{LARAVEL_URL}/api/ia/sse-update",
                json=payload,
                headers={
                    "Authorization": f"Bearer {CALLBACK_SECRET}",
                    "Content-Type": "application/json"
                },
                timeout=aiohttp.ClientTimeout(total=10)
            ) as response:
                if response.status != 200:
                    print(f"[{uuid}] Error al actualizar progreso: {response.status}")
    except Exception as e:
        print(f"[{uuid}] Excepción al actualizar progreso: {e}")


async def procesar_audio_worker(uuid: str, idioma: str):
    """
    Ejecuta el flujo completo de procesamiento para un archivo.
    El archivo ya está en /tmp/{uuid}.wav
    """
    ruta_archivo = os.path.join(RUTA_TEMPORAL, f"{uuid}.wav")
    metricas = {}
    inicio_total = time.time()

    # Verificar que el archivo existe
    if not os.path.exists(ruta_archivo):
        error_msg = f"Archivo no encontrado: {ruta_archivo}"
        print(f"[{uuid}] ERROR: {error_msg}")
        await notificar_a_laravel(uuid, "FALLIDO", error=error_msg)
        return

    try:
        print(f"[{uuid}] Iniciando procesamiento...")

        # ========================================
        # Etapa 1: ASR (0-40%)
        # ========================================
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=10, etapa="ASR")
        print(f"[{uuid}] 1. Ejecutando ASR...")
        inicio_asr = time.time()

        arg_idioma = None
        if idioma and idioma.lower() != "auto":
            arg_idioma = idioma.lower()

        resultado_asr = transcribir_audio(nombre_archivo=ruta_archivo, idioma=arg_idioma)
        metricas["asr"] = time.time() - inicio_asr

        datos_asr = resultado_asr["datos"]
        lista_palabras = datos_asr["palabras"]
        duracion_audio = resultado_asr["duracion"]

        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=40, etapa="ASR")

        # ========================================
        # Etapa 2: Diarización (40-70%)
        # ========================================
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=50, etapa="DIARIZACION")
        print(f"[{uuid}] 2. Solicitando diarización...")
        inicio_diar = time.time()

        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(
                    f"{URL_DIARIZADOR}/diarizar",
                    json={"ruta_archivo": ruta_archivo},
                    timeout=aiohttp.ClientTimeout(total=600)
                ) as response:
                    if response.status == 200:
                        resultado_diar = await response.json()
                        segmentos = resultado_diar.get("segmentos", [])
                    else:
                        raise Exception(f"Diarizador retornó {response.status}")
        except Exception as e:
            print(f"[{uuid}] Error en diarización: {e}")
            raise Exception(f"Fallo servicio diarización: {e}")

        metricas["diarizacion"] = time.time() - inicio_diar
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=70, etapa="DIARIZACION")

        # ========================================
        # Etapa 3: Alineación (70-85%)
        # ========================================
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=75, etapa="ALINEACION")
        print(f"[{uuid}] 3. Alineando resultados...")
        inicio_alineacion = time.time()

        salida_alineada = alinear_transcripcion(lista_palabras, segmentos)
        metricas["alineacion"] = time.time() - inicio_alineacion

        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=85, etapa="ALINEACION")

        # ========================================
        # Etapa 4: Suavizado (85-90%)
        # ========================================
        print(f"[{uuid}] 4. Suavizando...")
        inicio_suave = time.time()
        salida_suavizada = suavizar_transcripcion(salida_alineada)
        metricas["suavizado"] = time.time() - inicio_suave

        # ========================================
        # Etapa 5: Corrección Desconocidos (90-95%)
        # ========================================
        print(f"[{uuid}] 5. Corrigiendo desconocidos...")
        inicio_desc = time.time()
        salida_corregida = asignar_hablantes_desconocidos(salida_suavizada)
        metricas["correccion_desconocidos"] = time.time() - inicio_desc

        # ========================================
        # Etapa 6: Asignación de Roles (95-100%)
        # ========================================
        print(f"[{uuid}] 6. Asignando roles...")
        inicio_roles = time.time()
        salida_final = asignar_roles(salida_corregida)
        metricas["asignacion_roles"] = time.time() - inicio_roles

        # ========================================
        # Cálculo de métricas finales
        # ========================================
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

        # ========================================
        # Notificar completado a Laravel
        # ========================================
        await actualizar_progreso_laravel(uuid, "PROCESANDO", progreso=100, etapa="POST_PROCESADO")
        await notificar_a_laravel(uuid, "COMPLETADO", resultado=payload_resultado)

        print(f"[{uuid}] Procesamiento completado exitosamente")

    except Exception as e:
        import traceback
        traceback.print_exc()
        error_msg = f"Error interno: {str(e)}"
        print(f"[{uuid}] ERROR: {error_msg}")
        await notificar_a_laravel(uuid, "FALLIDO", error=error_msg)

    finally:
        # ========================================
        # Limpieza: ELIMINAR archivo temporal
        # ========================================
        if os.path.exists(ruta_archivo):
            os.remove(ruta_archivo)
            print(f"[{uuid}] Archivo temporal eliminado: {ruta_archivo}")

        # Limpiar referencia
        global procesando_actual
        procesando_actual = None


async def worker_principal():
    """
    Loop principal del worker: escucha la cola y procesa archivos.
    """
    print("=" * 60)
    print("WORKER DE PROCESAMIENTO INICIADO")
    print(f"URL Diarizador: {URL_DIARIZADOR}")
    print(f"URL Laravel: {LARAVEL_URL}")
    print(f"Ruta Temporal: {RUTA_TEMPORAL}")
    print("=" * 60)

    while True:
        try:
            # Esperar siguiente trabajo en la cola
            tarea = await cola_procesamiento.get()
            uuid = tarea["uuid"]
            idioma = tarea.get("idioma", "auto")

            print(f"\n[{uuid}] Procesando (idioma: {idioma})...")
            procesando_actual = uuid

            # Ejecutar procesamiento
            await procesar_audio_worker(uuid, idioma)

            # Marcar tarea como completada
            cola_procesamiento.task_done()

        except Exception as e:
            print(f"Error en worker principal: {e}")
            await asyncio.sleep(5)


async def agregar_a_cola(uuid: str, idioma: str = "auto"):
    """Agrega un trabajo a la cola de procesamiento."""
    await cola_procesamiento.put({
        "uuid": uuid,
        "idioma": idioma
    })

    posicion = cola_procesamiento.qsize()
    print(f"[{uuid}] Agregado a la cola. Posición: {posicion}")
    return posicion


def obtener_estado_cola() -> dict:
    """Devuelve el estado actual de la cola."""
    return {
        "estado": "ocupado" if procesando_actual else "libre",
        "peticiones_en_espera": cola_procesamiento.qsize(),
        "procesando_actual": procesando_actual
    }


# ==============================================================================
# PUNTO DE ENTRADA
# ==============================================================================

if __name__ == "__main__":
    print("Iniciando worker de procesamiento...")
    asyncio.run(worker_principal())
