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
            'GITLAB_CI' => 'false'
        ]));
    }

    public function testGitLabExecutionUsesConfiguredDatabase(): void
    {
        self::assertSame(DatabaseEnvironment::MODE_CI_DATABASE, DatabaseEnvironment::determineMode([
            'GITLAB_CI' => 'true'
        ]));
    }
}
