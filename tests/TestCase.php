<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Las pruebas no dependen de que alguien haya compilado el frontend:
        // sin esto, cualquier página falla si no existe public/build.
        $this->withoutVite();
    }
}
