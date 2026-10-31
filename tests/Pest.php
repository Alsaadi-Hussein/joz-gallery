<?php

use App\Models\User;
use App\Services\PriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Both suites run against the MariaDB testing database, so the schema and
| collation are the same as production.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

function owner(): User
{
    return User::factory()->owner()->create();
}

function staff(): User
{
    return User::factory()->create();
}

/**
 * A fresh service, so offers created by the test are seen.
 */
function prices(): PriceService
{
    app()->forgetScopedInstances();

    return app(PriceService::class);
}
