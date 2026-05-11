<?php

namespace App\Providers;

use App\Events\TranscripcionProcesada;
use App\Listeners\RegistrarTranscripcionCompletada;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * Mapa de eventos → listeners.
     * Punto de extensión para notificaciones: email, push, webhooks, etc.
     */
    protected $listen = [
        TranscripcionProcesada::class => [
            RegistrarTranscripcionCompletada::class,
        ],
    ];

    public function boot(): void
    {
        parent::boot();
    }
}
