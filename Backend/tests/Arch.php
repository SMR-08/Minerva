<?php

/*
|--------------------------------------------------------------------------
| Architecture Tests
|--------------------------------------------------------------------------
*/

// No debugging functions in production code
arch('no debugging functions')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'var_dump', 'die', 'exit']);

// Models should be in the App\Models namespace
arch('models are in App\Models namespace')
    ->expect('App\Models')
    ->toBeClasses()
    ->ignoring('App\Models\User');

// Controllers should extend base Controller
arch('controllers extend base controller')
    ->expect('App\Http\Controllers')
    ->toExtend('App\Http\Controllers\Controller')
    ->ignoring('App\Http\Controllers\Controller');

// Controllers use Request type hint
arch('controllers use Request type')
    ->expect('App\Http\Controllers')
    ->toUse('Illuminate\Http\Request');

// Seeders are in Database\Seeders
arch('seeders are in Database\Seeders')
    ->expect('Database\Seeders')
    ->toBeClasses();

// Factories are in Database\Factories
arch('factories are in Database\Factories')
    ->expect('Database\Factories')
    ->toBeClasses()
    ->ignoring('Database\Factories\UserFactory');

// No direct env() calls in application code (use config instead)
arch('no env calls in controllers')
    ->expect('App\Http\Controllers')
    ->not->toUse('env');

// No direct env() calls in Jobs
arch('no env calls in jobs')
    ->expect('App\Jobs')
    ->not->toUse('env');
