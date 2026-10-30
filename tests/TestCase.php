<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Roda antes de qualquer migração: o RefreshDatabase apaga o banco em que estiver.
     * O phpunit.xml não garante isso, porque variável real do processo e config:cache
     * vencem o que ele define.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        self::abortUnlessTestingDatabase(DB::connection()->getDatabaseName());

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Os testes conferem o back: não dependem do build do front.
        $this->withoutVite();
    }

    public static function abortUnlessTestingDatabase(string $database): void
    {
        if ($database !== 'testing') {
            throw new RuntimeException("Abortado: os testes só rodam no banco [testing], não em [{$database}].");
        }
    }
}
