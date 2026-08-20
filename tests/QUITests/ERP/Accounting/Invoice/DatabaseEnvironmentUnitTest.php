<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseEnvironmentUnitTest extends TestCase
{
    public function testLocalExecutionAlwaysUsesSqlite(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([]));
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'false',
            'DB_VENDOR' => 'mysql',
            'CI_JOB_ID' => '123',
            'QUIQQER_DB_HOST' => 'db',
            'QUIQQER_DB_NAME' => 'quiqqer'
        ]));
    }

    public function testCompleteGitLabDatabaseEnvironmentUsesCiDatabase(): void
    {
        foreach (['mariadb', 'mysql'] as $vendor) {
            self::assertSame(DatabaseEnvironment::MODE_CI_DATABASE, DatabaseEnvironment::determineMode([
                'GITLAB_CI' => 'true',
                'DB_VENDOR' => $vendor,
                'CI_JOB_ID' => '123',
                'QUIQQER_DB_HOST' => 'db',
                'QUIQQER_DB_NAME' => 'quiqqer'
            ]));
        }
    }

    public function testIncompleteGitLabDatabaseEnvironmentFailsClosed(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('DB_VENDOR');

        DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'true',
            'CI_JOB_ID' => '123',
            'QUIQQER_DB_HOST' => 'db',
            'QUIQQER_DB_NAME' => 'quiqqer'
        ]);
    }

    public function testGitLabDatabaseMustUseIsolatedServiceHost(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('host "db"');

        DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'true',
            'DB_VENDOR' => 'mysql',
            'CI_JOB_ID' => '123',
            'QUIQQER_DB_HOST' => 'production.example.invalid',
            'QUIQQER_DB_NAME' => 'quiqqer'
        ]);
    }

    public function testGitLabDatabaseMustBeDisposableTestDatabase(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('disposable database');

        DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'true',
            'DB_VENDOR' => 'mysql',
            'CI_JOB_ID' => '123',
            'QUIQQER_DB_HOST' => 'db',
            'QUIQQER_DB_NAME' => 'customer_production'
        ]);
    }
}
