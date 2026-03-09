from fastapi import FastAPI, HTTPException, Body
import os
import subprocess
import tempfile

app = FastAPI()

# Configuracion
SENKO_DEVICE = os.environ.get("SENKO_DEVICE", "cuda")
SENKO_VAD = os.environ.get("SENKO_VAD", "pyannote")
SENKO_CLUSTERING = os.environ.get("SENKO_CLUSTERING", "auto")
RUTA_ENTRADA = os.environ.get("RUTA_ENTRADA", "/app/audios/entrada")

# Variable global para el diarizador (singleton, se mantiene cargado)
diarizador = None

def obtener_diarizador():
    """Carga el diarizador Senko si no esta en memoria (singleton)."""
    global diarizador
    if diarizador is None:
        import senko
        print(f"Inicializando Senko (device={SENKO_DEVICE}, vad={SENKO_VAD}, clustering={SENKO_CLUSTERING})...")
        diarizador = senko.Diarizer(
            device=SENKO_DEVICE,
            vad=SENKO_VAD,
            clustering=SENKO_CLUSTERING,
            warmup=True,
            quiet=False,
        )
        print("Senko inicializado y listo.")
    return diarizador

def convertir_a_wav_16k(ruta_origen: str) -> str:
    """
    Convierte el audio a WAV 16kHz mono 16-bit usando ffmpeg.
    Senko requiere este formato exacto.
    Retorna la ruta del archivo convertido (temporal).
    """
    # Si ya es WAV, verificar formato; si no, convertir siempre
    archivo_tmp = tempfile.NamedTemporaryFile(suffix=".wav", delete=False)
    ruta_tmp = archivo_tmp.name
    archivo_tmp.close()

    try:
        cmd = [
            "ffmpeg", "-y",
            "-i", ruta_origen,
            "-ar", "16000",      # 16kHz
            "-ac", "1",          # Mono
            "-sample_fmt", "s16", # 16-bit
            "-f", "wav",
            ruta_tmp
        ]
        resultado = subprocess.run(
            cmd, capture_output=True, text=True, timeout=120
        )
        if resultado.returncode != 0:
            raise RuntimeError(f"ffmpeg fallo: {resultado.stderr}")
        
        print(f"Audio convertido a WAV 16kHz mono: {ruta_tmp}")
        return ruta_tmp
        
    except Exception as e:
        # Limpiar temporal si falla
        if os.path.exists(ruta_tmp):
            os.unlink(ruta_tmp)
        raise e

@app.post("/diarizar")
async def endpoint_diarizar(datos: dict = Body(...)):
    """
    Recibe {"ruta_archivo": "entrada/nombre.wav"} relativo a RUTA_ENTRADA.
    Devuelve lista de segmentos con hablantes.
    """
    nombre_archivo = datos.get("ruta_archivo")
    if not nombre_archivo:
        raise HTTPException(status_code=400, detail="ruta_archivo es requerido")
    
    # Limpiar prefijo de carpeta si viene incluido
    clean_filename = nombre_archivo
    folder_prefix = os.path.basename(RUTA_ENTRADA) + "/"  # ej: "entrada/"
    
    if clean_filename.startswith(folder_prefix):
        clean_filename = clean_filename[len(folder_prefix):]
        
    ruta_absoluta = os.path.join(RUTA_ENTRADA, clean_filename)
    
    if not os.path.exists(ruta_absoluta):
        raise HTTPException(status_code=404, detail=f"Archivo no encontrado: {ruta_absoluta}")

    ruta_wav_temporal = None
    
    try:
        # 1. Convertir audio a formato requerido por Senko (WAV 16kHz mono 16-bit)
        ruta_wav_temporal = convertir_a_wav_16k(ruta_absoluta)
        
        # 2. Ejecutar diarizacion con Senko
        pipeline = obtener_diarizador()
        print(f"Iniciando diarizacion Senko para: {ruta_absoluta}")
        resultado = pipeline.diarize(ruta_wav_temporal, generate_colors=False)
        
        # 3. Adaptar formato de salida: Senko {start, end, speaker} -> {inicio, fin, hablante}
        segmentos_salida = []
        segmentos_fuente = resultado.get("merged_segments", resultado.get("raw_segments", []))
        
        for seg in segmentos_fuente:
            segmentos_salida.append({
                "inicio": seg["start"],
                "fin": seg["end"],
                "hablante": seg["speaker"]
            })
        
        # Log de estadisticas
        stats = resultado.get("timing_stats", {})
        n_hablantes = resultado.get("merged_speakers_detected", "?")
        print(f"Diarizacion completada: {n_hablantes} hablantes detectados.")
        if stats:
            print(f"  Tiempos => Total: {stats.get('total_time', 0):.2f}s | "
                  f"VAD: {stats.get('vad_time', 0):.2f}s | "
                  f"Embeddings: {stats.get('embeddings_time', 0):.2f}s | "
                  f"Clustering: {stats.get('clustering_time', 0):.2f}s")
        
        return {"segmentos": segmentos_salida}

    except Exception as e:
        print(f"Error en diarizacion: {e}")
        raise HTTPException(status_code=500, detail=str(e))
    
    finally:
        # Limpiar archivo temporal de conversion
        if ruta_wav_temporal and os.path.exists(ruta_wav_temporal):
            os.unlink(ruta_wav_temporal)

@app.get("/estado")
def verificacion_estado():
    return {"estado": "servicio_diarizador_senko_activo"}
