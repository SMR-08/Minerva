<?php

namespace Tests;

use Database\Seeders\DatabaseSeeder;

/**
 * Trait para tests que usan RefreshDatabase.
 * Ejecuta el DatabaseSeeder automáticamente después de cada migración
 * para que los roles y estados de usuario estén siempre disponibles.
 *
 * Los seed data se movieron de la migración al seeder por buenas prácticas,
 * pero los tests los necesitan. Este trait cierra esa brecha.
 */
trait SeedsDatabase
{
    /**
     * Ejecutar el seeder después de refrescar la BD.
     */
    protected function setUpSeedsDatabase(): void
    {
        $this->seed(DatabaseSeeder::class);
    }
}
