import os
import asyncio
import time
import requests
import json
import torch
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import Optional, Dict, List

# Importacion de modulos locales
from ASR.asr import transcribir_audio

# CONFIGURACION
# --- RED ---
URL_DIARIZADOR = os.environ.get("URL_DIARIZADOR", "http://diarizador:8000")

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

# --- ENDPOINTS ---

@app.get("/estado", response_model=RespuestaEstado)
def verificar_estado():
    """Verifica si el servicio esta activo y si detecta GPU."""
    estado_gpu = torch.cuda.get_device_name(0) if torch.cuda.is_available() else "No disponible"
    return {"estado": "API ARS activa", "gpu": estado_gpu}

@app.get("/estado_cola", response_model=RespuestaCola)
async def obtener_estado_cola():
    """Devuelve si el sistema esta ocupado y cuantos esperan."""
    return {
        "estado": "ocupado" if procesamiento.locked() else "libre",
        "peticiones_en_espera": peticiones_en_cola
    }

# --- LOGICA DE NEGOCIO ---

def alinear_transcripcion(palabras: list, segmentos: list) -> list:
    """
    Alinea palabras (ASR) con segmentos (Diarizacion) por superposicion temporal.
    Esta version es 'fiel' al Diarizador, dejando la heuristica para pasos posteriores.
    """
    turnos_alineados = []
    turno_actual = None
    
    # Ordenar por seguridad
    palabras = sorted(palabras, key=lambda x: x["inicio"])
    segmentos = sorted(segmentos, key=lambda x: x["inicio"])
    
    for item_palabra in palabras:
        p_inicio = item_palabra["inicio"]
        p_fin = item_palabra["fin"]
        p_texto = item_palabra["palabra"]
        # Buscamos un punto de equilibrio (60%) para decidir el hablante
        p_eval = p_inicio + (p_fin - p_inicio) * 0.6
        
        hablante_detectado = "DESCONOCIDO"
        for seg in segmentos:
            if seg["inicio"] <= p_eval <= seg["fin"]:
                hablante_detectado = seg["hablante"]
                break
        
        # Agrupacion simple
        if turno_actual and turno_actual["hablante"] == hablante_detectado:
            turno_actual["texto"] += " " + p_texto
            turno_actual["fin"] = p_fin
        else:
            if turno_actual:
                turnos_alineados.append(turno_actual)
            turno_actual = {
                "hablante": hablante_detectado,
                "inicio": p_inicio,
                "fin": p_fin,
                "texto": p_texto
            }
            
    if turno_actual:
        turnos_alineados.append(turno_actual)
        
    return turnos_alineados

def suavizar_transcripcion(turnos: list) -> list:
    """
    Fusiona segmentos espurios. 
    REGLA: Si B es muy CORTO (<1.0s) y empieza por minúscula, se considera error de diarización y se une a A.
    """
    if len(turnos) < 2:
        return turnos
    
    turnos_corregidos = []
    i = 0
    while i < len(turnos):
        actual = turnos[i].copy()
        
        while i + 1 < len(turnos):
            siguiente = turnos[i+1]
            texto_sig = siguiente["texto"].strip()
            # ¿Es una interrupción protegida? (Mayúscula y no es 'I')
            es_interrupcion_real = (texto_sig and texto_sig[0].isupper() and texto_sig != "I")
            duracion_sig = siguiente["fin"] - siguiente["inicio"]
            gap = siguiente["inicio"] - actual["fin"]
            
            # Unir si: mismo hablante O (es una falla corta en minúscula con gap pequeño)
            if actual["hablante"] == siguiente["hablante"]:
                actual["texto"] += " " + siguiente["texto"]
                actual["fin"] = siguiente["fin"]
                i += 1
            elif not es_interrupcion_real and duracion_sig < 1.0 and gap < 0.5:
                # Robo controlado: solo robamos segmentos insignificantes en minúscula
                actual["texto"] += " " + siguiente["texto"]
                actual["fin"] = siguiente["fin"]
                i += 1
            else:
                break
        
        turnos_corregidos.append(actual)
        i += 1
        
    return turnos_corregidos

def asignar_hablantes_desconocidos(turnos: list) -> list:
    """
    Asigna segmentos 'DESCONOCIDO' basandose en el contexto inmediato.
    Si un desconocido está entre el mismo hablante, se fusiona.
    Si está en un cambio de turno, se le asigna al que tiene más probabilidad por el texto (mayúsculas).
    """
    if len(turnos) < 2:
        return turnos
        
    hablantes_conocidos = set(t["hablante"] for t in turnos if t["hablante"] != "DESCONOCIDO")
    res = []
    
    for i, t in enumerate(turnos):
        if t["hablante"] == "DESCONOCIDO":
            previo = res[-1]["hablante"] if res else "DESCONOCIDO"
            siguiente = turnos[i+1]["hablante"] if i+1 < len(turnos) else "DESCONOCIDO"
            
            texto = t["texto"].strip()
            es_frontera = texto and texto[0].isupper() and texto != "I"
            
            if previo != "DESCONOCIDO" and previo == siguiente:
                 # Sandwich: A -> DESC -> A  => Probablemente es el interlocutor B o ruido de A
                 if not es_frontera:
                      t["hablante"] = previo
                 else:
                      # Si hay mayúscula, es probable que sea el "otro"
                      otros = hablantes_conocidos - {previo}
                      t["hablante"] = list(otros)[0] if otros else previo
            elif previo != "DESCONOCIDO":
                 t["hablante"] = previo if not es_frontera else (siguiente if siguiente != "DESCONOCIDO" else previo)
        res.append(t)
    
    # Consolidar de nuevo para evitar mini-fragmentos del mismo hablante tras la corrección
    if not res: return []
    final = [res[0]]
    for t in res[1:]:
        if t["hablante"] == final[-1]["hablante"]:
            final[-1]["texto"] += " " + t["texto"]
            final[-1]["fin"] = t["fin"]
        else:
            final.append(t)
    return final

def asignar_roles(turnos: list) -> list:
    """
    Asigna roles 'Profesor' y 'Alumnos'.
    Profesor = Hablante con mayor duracion total acumulada.
    """
    if not turnos:
        return []
        
    duracion_por_hablante = {}
    for t in turnos:
        h = t["hablante"]
        d = t["fin"] - t["inicio"]
        duracion_por_hablante[h] = duracion_por_hablante.get(h, 0) + d
        
    if not duracion_por_hablante:
        return turnos
        
    id_profesor = max(duracion_por_hablante, key=duracion_por_hablante.get)
    
    turnos_finales = []
    for t in turnos:
        nuevo_turno = t.copy()
        if t["hablante"] == id_profesor:
            nuevo_turno["hablante"] = "Profesor"
        else:
            # Cualquier otro es Alumnos (conjunto)
            nuevo_turno["hablante"] = "Alumnos"
        turnos_finales.append(nuevo_turno)
        
    return turnos_finales

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