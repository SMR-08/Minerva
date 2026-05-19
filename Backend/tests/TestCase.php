<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Ejecuta el DatabaseSeeder para que los tests que usan RefreshDatabase
     * tengan los roles y estados de usuario disponibles.
     * (Se movieron de la migración al seeder para cumplir con buenas prácticas)
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Seed the database. Los tests de Feature deben llamar a esto
     * después de RefreshDatabase.
     */
    protected function seedDatabase(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }
}
