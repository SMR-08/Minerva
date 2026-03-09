import os
import uuid
import torch
import json
import gc
from qwen_asr import Qwen3ASRModel
from typing import Optional, Dict, Any

# CONFIGURACION DEL MODELO
MODELO_ASR = os.getenv("MODELO_ASR", "Qwen/Qwen3-ASR-1.7B")
MODELO_ALIGNER = os.getenv("MODELO_ALIGNER", "Qwen/Qwen3-ForcedAligner-0.6B")
DISPOSITIVO = os.getenv("DISPOSITIVO_ASR", "cuda:0" if torch.cuda.is_available() else "cpu")

# --- CONFIGURACION RUTAS Y ARCHIVOS ---
RUTA_ENTRADA = os.getenv("RUTA_ENTRADA", "/app/audios/entrada")
RUTA_SALIDA_TRANSCRIPCIONES = os.getenv("RUTA_SALIDA_TRANSCRIPCIONES", "/app/transcripciones")
NOMBRE_ARCHIVO_TRANSCRIPCION = os.getenv("NOMBRE_ARCHIVO_TRANSCRIPCION", "transcripcion.txt")

# MAPEO DE CODIGOS ISO A NOMBRES DE IDIOMA PARA QWEN3-ASR
# Qwen3-ASR espera nombres completos ("Spanish") en vez de codigos ISO ("es")
MAPA_IDIOMAS = {
    "es": "Spanish", "en": "English", "fr": "French", "de": "German",
    "it": "Italian", "pt": "Portuguese", "nl": "Dutch", "pl": "Polish",
    "ru": "Russian", "ja": "Japanese", "ko": "Korean", "zh": "Chinese",
    "ar": "Arabic", "hi": "Hindi", "tr": "Turkish", "vi": "Vietnamese",
    "th": "Thai", "id": "Indonesian", "ms": "Malay", "da": "Danish",
    "sv": "Swedish", "fi": "Finnish", "el": "Greek", "cs": "Czech",
    "ro": "Romanian", "hu": "Hungarian", "fa": "Persian", "fil": "Filipino",
    "mk": "Macedonian", "yue": "Cantonese",
}


def transcribir_audio(
    nombre_archivo: str,
    idioma: str = None,
) -> Dict[str, Any]:
    """
    Transcribe el archivo de audio ubicado en /app/audios/entrada/ usando Qwen3-ASR.

    Argumentos:
        nombre_archivo: Nombre del archivo en la carpeta de entrada.
        idioma: Codigo de idioma ('es', 'en') o None para deteccion automatica.

    Retorna:
        Dict con la ruta del texto generado, los datos estructurados y la duracion.
    """
    ruta_entrada = os.path.join(RUTA_ENTRADA, nombre_archivo)

    if not os.path.exists(ruta_entrada):
        raise FileNotFoundError(f"Archivo de audio no encontrado: {ruta_entrada}")

    # Gestion de Memoria VRAM - Limpieza preventiva
    gc.collect()
    if torch.cuda.is_available():
        torch.cuda.empty_cache()

    # Resolver idioma: codigo ISO -> nombre completo para Qwen3-ASR
    nombre_idioma = None
    if idioma:
        nombre_idioma = MAPA_IDIOMAS.get(idioma.lower(), idioma)

    print(f"Cargando modelo {MODELO_ASR} en {DISPOSITIVO}...")
    modelo = Qwen3ASRModel.from_pretrained(
        MODELO_ASR,
        dtype=torch.bfloat16,
        device_map=DISPOSITIVO,
        forced_aligner=MODELO_ALIGNER,
        forced_aligner_kwargs=dict(
            dtype=torch.bfloat16,
            device_map=DISPOSITIVO,
        ),
        max_inference_batch_size=32,
        max_new_tokens=4096,  # Valor alto para audios largos
    )

    try:
        # Transcripcion con timestamps
        print(f"Transcribiendo: {ruta_entrada} (idioma: {nombre_idioma or 'auto'})...")
        resultados = modelo.transcribe(
            audio=ruta_entrada,
            language=nombre_idioma,
            return_time_stamps=True,
        )

        resultado = resultados[0]
        texto_final = resultado.text.strip()
        idioma_detectado = resultado.language

        # Extraer duracion del audio con soundfile
        import soundfile as sf
        info_audio = sf.info(ruta_entrada)
        duracion_total = info_audio.duration

        print(f"Duracion del audio: {duracion_total:.2f}s")
        print(f"Idioma detectado: {idioma_detectado}")

        # Convertir timestamps de Qwen3-ASR al formato esperado por el orquestador
        # Qwen3-ASR: {text, start_time, end_time}
        # Formato esperado: {palabra, inicio, fin, probabilidad}
        lista_palabras = []
        if resultado.time_stamps:
            for ts in resultado.time_stamps:
                lista_palabras.append({
                    "palabra": ts.text.strip(),
                    "inicio": ts.start_time,
                    "fin": ts.end_time,
                    "probabilidad": 1.0  # Qwen3-ASR no expone probabilidad por palabra
                })

    finally:
        # Liberacion de recursos
        try:
            del modelo
        except:
            pass
        gc.collect()
        if torch.cuda.is_available():
            torch.cuda.empty_cache()

    # Generacion de identificadores y rutas de salida
    nombre_base = os.path.splitext(nombre_archivo)[0]
    uuid_simple = uuid.uuid4().hex[:8]
    nombre_carpeta = f"{nombre_base}_{uuid_simple}"

    ruta_base_salida = RUTA_SALIDA_TRANSCRIPCIONES

    ruta_directorio_salida = os.path.join(ruta_base_salida, nombre_carpeta)
    os.makedirs(ruta_directorio_salida, exist_ok=True)

    ruta_archivo_texto = os.path.join(ruta_directorio_salida, NOMBRE_ARCHIVO_TRANSCRIPCION)

    with open(ruta_archivo_texto, "w", encoding="utf-8") as f:
        f.write(texto_final)

    # Estructura de datos en memoria (compatible con el orquestador)
    datos_resultado = {
        "archivo_origen": nombre_archivo,
        "idioma_detectado": idioma_detectado if idioma is None else idioma,
        "duracion": duracion_total,
        "texto": texto_final,
        "palabras": lista_palabras
    }

    return {
        "ruta_texto": ruta_archivo_texto,
        "datos": datos_resultado,
        "duracion": duracion_total
    }

if __name__ == "__main__":
    import sys
    archivo = sys.argv[1] if len(sys.argv) > 1 else "audio.wav"
    try:
        res = transcribir_audio(archivo)
        print(f"Proceso finalizado. Texto en: {res['ruta_texto']}")
    except Exception as e:
        print(f"Error: {e}")