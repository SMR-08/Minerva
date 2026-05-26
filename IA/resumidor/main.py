"""
Minerva Resumidor — Microservicio de generación de resúmenes.

Recibe texto de una transcripción, devuelve resumen estructurado.
Modelo: Qwen3.5-0.8B (configurable via SUMMARY_MODEL).
Se usa en modo full (16GB+ VRAM) como servicio independiente.

API:
  POST /resumir  — genera resumen
  GET  /estado   — healthcheck
"""

import os
import torch
from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from transformers import AutoModelForCausalLM, AutoTokenizer

# ==============================================================================
# CONFIGURACIÓN
# ==============================================================================

SUMMARY_MODEL = os.environ.get("SUMMARY_MODEL", "Qwen/Qwen3.5-0.8B")
DEVICE = os.environ.get("DISPOSITIVO", "cuda:0" if torch.cuda.is_available() else "cpu")
MAX_INPUT_TOKENS = int(os.environ.get("SUMMARY_INPUT_MAX_TOKENS", "32768"))

SYSTEM_PROMPT = """Eres un asistente que resume clases universitarias.
Genera un resumen estructurado en markdown del texto proporcionado.
El resumen debe incluir:
- Tema principal
- Puntos clave (lista con viñetas)
- Preguntas de alumnos (si las hay)
Sé conciso. Máximo 500 palabras.
Responde en el mismo idioma que el texto. Si no puedes detectarlo, usa español."""

# ==============================================================================
# CARGA DEL MODELO (al inicio, siempre en memoria)
# ==============================================================================

print(f"[resumidor] Cargando modelo {SUMMARY_MODEL} en {DEVICE}...")
tokenizer = AutoTokenizer.from_pretrained(SUMMARY_MODEL, trust_remote_code=True)
model = AutoModelForCausalLM.from_pretrained(
    SUMMARY_MODEL,
    torch_dtype=torch.bfloat16,
    device_map=DEVICE,
    trust_remote_code=True,
)
model.eval()
print(f"[resumidor] Modelo cargado. VRAM: {torch.cuda.memory_allocated()/1048576:.0f}MB")


# ==============================================================================
# API
# ==============================================================================

app = FastAPI(title="Minerva Resumidor")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)


class PeticionResumen(BaseModel):
    texto: str
    idioma: str = "es"
    max_tokens: int = 1024


class RespuestaResumen(BaseModel):
    resumen: str


@app.post("/resumir", response_model=RespuestaResumen)
def resumir(peticion: PeticionResumen):
    if not peticion.texto or len(peticion.texto.strip()) < 50:
        raise HTTPException(status_code=400, detail="Texto demasiado corto")

    messages = [
        {"role": "system", "content": SYSTEM_PROMPT},
        {"role": "user", "content": f"Resume la siguiente transcripción de clase:\n\n{peticion.texto}"},
    ]

    input_text = tokenizer.apply_chat_template(
        messages, tokenize=False, add_generation_prompt=True
    )
    inputs = tokenizer(
        input_text,
        return_tensors="pt",
        truncation=True,
        max_length=MAX_INPUT_TOKENS,
    ).to(DEVICE)

    with torch.no_grad():
        outputs = model.generate(
            **inputs,
            max_new_tokens=peticion.max_tokens,
            temperature=1.0,
            top_p=1.0,
            top_k=20,
            do_sample=True,
        )

    resumen = tokenizer.decode(
        outputs[0][inputs["input_ids"].shape[1]:],
        skip_special_tokens=True,
    )

    if len(resumen.strip()) < 20:
        resumen = "No se pudo generar un resumen adecuado."

    return {"resumen": resumen}


@app.get("/estado")
def estado():
    gpu_name = torch.cuda.get_device_name(0) if torch.cuda.is_available() else "CPU"
    vram_mb = torch.cuda.memory_allocated() / 1048576 if torch.cuda.is_available() else 0
    return {
        "estado": "activo",
        "modelo": SUMMARY_MODEL,
        "gpu": gpu_name,
        "vram_mb": round(vram_mb),
        "max_input_tokens": MAX_INPUT_TOKENS,
    }

