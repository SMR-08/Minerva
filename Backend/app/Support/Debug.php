<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * Sistema de debug granular por módulo.
 * Solo emite en desarrollo (APP_ENV != production).
 * Cada módulo se activa/desactiva con su variable DEBUG_*.
 */
class Debug
{
    public static function audio(string $message, array $context = []): void
    {
        self::emit('audio', $message, $context);
    }

    public static function auth(string $message, array $context = []): void
    {
        self::emit('auth', $message, $context);
    }

    public static function ia(string $message, array $context = []): void
    {
        self::emit('ia', $message, $context);
    }

    public static function queue(string $message, array $context = []): void
    {
        self::emit('queue', $message, $context);
    }

    public static function sse(string $message, array $context = []): void
    {
        self::emit('sse', $message, $context);
    }

    public static function db(string $message, array $context = []): void
    {
        self::emit('db', $message, $context);
    }

    private static function emit(string $module, string $message, array $context): void
    {
        // En producción, debug se ignora siempre
        if (app()->environment('production')) {
            return;
        }

        if (!config("app.debug_modules.{$module}")) {
            return;
        }

        // Truncar valores largos en contexto para seguridad y legibilidad
        $context = self::sanitize($context);

        $tag = strtoupper($module);
        Log::channel('debug')->debug("[{$tag}] {$message}", $context);
    }

    private static function sanitize(array $context, int $maxLength = 500): array
    {
        $result = [];
        foreach ($context as $key => $value) {
            if (is_string($value) && strlen($value) > $maxLength) {
                $result[$key] = substr($value, 0, $maxLength) . ' [TRUNCATED]';
            } elseif (is_array($value)) {
                $result[$key] = self::sanitize($value, $maxLength);
            } else {
                $result[$key] = $value;
            }
            // Nunca exponer secrets completos
            if (in_array(strtolower($key), ['token', 'secret', 'password', 'api_key'])) {
                $result[$key] = is_string($value)
                    ? substr($value, 0, 6) . '...' . substr($value, -4)
                    : '[REDACTED]';
            }
        }
        return $result;
    }
}
