<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice;

final class DatabaseEnvironment
{
    public const MODE_CI_DATABASE = 'ci-database';
    public const MODE_SQLITE = 'sqlite';

    /**
     * @param array<string, string|false> $environment
     */
    public static function determineMode(array $environment): string
    {
        return ($environment['GITLAB_CI'] ?? false) === 'true'
            ? self::MODE_CI_DATABASE
            : self::MODE_SQLITE;
    }

    public static function getMode(): string
    {
        $environment = getenv();

        return self::determineMode(is_array($environment) ? $environment : []);
    }

    public static function usesCiDatabase(): bool
    {
        return self::getMode() === self::MODE_CI_DATABASE;
    }
}
