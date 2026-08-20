<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;

class DatabaseEnvironmentUnitTest extends TestCase
{
    public function testLocalExecutionAlwaysUsesSqlite(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([]));
        self::assertSame(DatabaseEnvironment::MODE_SQLITE, DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'false',
            'DB_VENDOR' => 'future-database'
        ]));
    }

    public function testGitLabExecutionUsesConfiguredDatabaseRegardlessOfVendor(): void
    {
        foreach (['mariadb', 'mysql', 'postgresql', 'sqlite', 'future-database'] as $vendor) {
            self::assertSame(DatabaseEnvironment::MODE_CI_DATABASE, DatabaseEnvironment::determineMode([
                'GITLAB_CI' => 'true',
                'DB_VENDOR' => $vendor
            ]));
        }
    }
}
