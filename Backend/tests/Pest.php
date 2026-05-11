<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a
| specific PHPUnit test case class. By default, that class is "PHPUnit\Framework\TestCase".
|
*/

pest()->extends(Tests\TestCase::class)->use(Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Database Seeding
|--------------------------------------------------------------------------
|
| Los roles y estados de usuario se movieron de la migración al
| DatabaseSeeder por buenas prácticas. Los tests los necesitan,
| así que ejecutamos el seeder antes de cada test de Feature.
|
*/
uses()->beforeEach(function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| Pest provides a fluent expectation API that is both readable and
| expressive. You can extend it with your own expectations here.
|
*/

expect()->extend('toBeAValidEmail', function () {
    return $this->toMatch('/^[\w\.-]+@[\w\.-]+\.\w+$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is primarily focused on providing a beautiful API for
| writing tests, you can also define helper functions here.
|
*/

function createUsuario(array $attributes = [])
{
    return \App\Models\Usuario::factory()->create($attributes);
}
