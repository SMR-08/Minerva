"""
Minerva IA — Logging estructurado JSON.
Mismo schema base que Laravel y Nginx para correlación entre servicios.
"""

import json
import os
import sys
import time
from datetime import datetime, timezone


SERVICE_NAME = os.environ.get("SERVICE_NAME", "asr")
DEBUG_IA = os.environ.get("DEBUG_IA", "0") == "1"


def _emit(level: str, message: str, **context):
    """Emite un log JSON estructurado a stdout."""
    entry = {
        "timestamp": datetime.now(timezone.utc).isoformat(),
        "level": level,
        "service": SERVICE_NAME,
        "message": message,
    }
    if context:
        entry["context"] = context
    print(json.dumps(entry, ensure_ascii=False), flush=True)


def info(message: str, **context):
    _emit("info", message, **context)


def warning(message: str, **context):
    _emit("warning", message, **context)


def error(message: str, **context):
    _emit("error", message, **context)


def critical(message: str, **context):
    _emit("critical", message, **context)


def debug(message: str, **context):
    """Solo emite si DEBUG_IA=1. Nunca en producción."""
    if not DEBUG_IA:
        return
    # Truncar valores largos
    sanitized = {}
    for k, v in context.items():
        if isinstance(v, str) and len(v) > 500:
            sanitized[k] = v[:500] + " [TRUNCATED]"
        else:
            sanitized[k] = v
    _emit("debug", message, **sanitized)


class _Logger:
    """Wrapper para usar como logger.info(msg, key=val) estilo structlog."""
    def __init__(self, name: str):
        self.name = name

    def info(self, message: str, **ctx):
        _emit("info", message, logger_name=self.name, **ctx)

    def warning(self, message: str, **ctx):
        _emit("warning", message, logger_name=self.name, **ctx)

    def error(self, message: str, **ctx):
        _emit("error", message, logger_name=self.name, **ctx)

    def debug(self, message: str, **ctx):
        if DEBUG_IA:
            _emit("debug", message, logger_name=self.name, **ctx)


def get_logger(name: str = "minerva") -> _Logger:
    """Devuelve un logger con nombre para el módulo."""
    return _Logger(name)
