"""
GPUModelManager — Gestión de carga/descarga de modelos en GPU.

Dos modos:
  - compact (8GB VRAM): solo 1 modelo a la vez, load/unload entre tareas
  - full (16GB+ VRAM): todos precargados al inicio

Configurable via GPU_MODE en .env.
"""

import asyncio
import gc
import os
import time
from typing import Any, Callable, Optional

import torch

from logger import get_logger

logger = get_logger(__name__)


class GPUModelManager:
    def __init__(self):
        self.mode = os.environ.get("GPU_MODE", "compact")
        self.models: dict[str, Any] = {}
        self.loaded: Optional[str] = None
        self._lock = asyncio.Lock()
        self._loaders: dict[str, Callable] = {}

        logger.info("GPUModelManager iniciado",
                    mode=self.mode,
                    device=os.environ.get("DISPOSITIVO_ASR", "cuda:0"))

    def register_loader(self, name: str, loader: Callable):
        """Registra una función de carga para un modelo."""
        self._loaders[name] = loader

    async def ensure_loaded(self, model_name: str) -> Any:
        """Garantiza que el modelo está en GPU. Retorna la instancia."""
        async with self._lock:
            if model_name in self.models:
                return self.models[model_name]

            if self.mode == "full":
                # En modo full, cargar sin descargar otros
                return await self._load(model_name)

            # Modo compact: descargar el actual antes de cargar otro
            if self.loaded and self.loaded != model_name:
                await self._unload(self.loaded)

            model = await self._load(model_name)
            self.loaded = model_name
            return model

    async def _load(self, model_name: str) -> Any:
        """Carga un modelo en GPU via run_in_executor (blocking → async)."""
        if model_name not in self._loaders:
            raise ValueError(f"No hay loader registrado para: {model_name}")

        loader = self._loaders[model_name]
        loop = asyncio.get_event_loop()

        start = time.time()
        logger.info("Cargando modelo en GPU", model=model_name)

        model = await loop.run_in_executor(None, loader)
        self.models[model_name] = model

        elapsed = round(time.time() - start, 2)
        vram_mb = torch.cuda.memory_allocated() / 1048576 if torch.cuda.is_available() else 0
        logger.info("Modelo cargado",
                    model=model_name,
                    elapsed_s=elapsed,
                    vram_mb=round(vram_mb))

        return model

    async def _unload(self, model_name: str):
        """Descarga un modelo de GPU y libera VRAM."""
        if model_name not in self.models:
            return

        logger.info("Descargando modelo de GPU", model=model_name)

        del self.models[model_name]

        if torch.cuda.is_available():
            torch.cuda.empty_cache()
            torch.cuda.synchronize()

        gc.collect()
        self.loaded = None

        vram_mb = torch.cuda.memory_allocated() / 1048576 if torch.cuda.is_available() else 0
        logger.info("Modelo descargado", model=model_name, vram_mb=round(vram_mb))

    async def preload_all(self):
        """Precarga todos los modelos registrados (modo full)."""
        for name in self._loaders:
            await self.ensure_loaded(name)
        logger.info("Todos los modelos precargados", count=len(self.models))

    def get_status(self) -> dict:
        """Estado actual del manager."""
        return {
            "mode": self.mode,
            "loaded": self.loaded,
            "registered": list(self._loaders.keys()),
            "in_memory": list(self.models.keys()),
            "vram_mb": round(torch.cuda.memory_allocated() / 1048576)
                       if torch.cuda.is_available() else 0,
        }

