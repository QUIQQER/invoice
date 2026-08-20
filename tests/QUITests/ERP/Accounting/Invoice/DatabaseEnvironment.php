<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice;

use RuntimeException;

final class DatabaseEnvironment
{
    public const MODE_CI_DATABASE = 'ci-database';
    public const MODE_SQLITE = 'sqlite';

    /**
     * @param array<string, string|false> $environment
     */
    public static function determineMode(array $environment): string
    {
        if (($environment['GITLAB_CI'] ?? false) !== 'true') {
            return self::MODE_SQLITE;
        }

        $vendor = $environment['DB_VENDOR'] ?? false;
        $jobId = $environment['CI_JOB_ID'] ?? false;
        $databaseHost = $environment['QUIQQER_DB_HOST'] ?? false;
        $databaseName = $environment['QUIQQER_DB_NAME'] ?? false;

        if (!in_array($vendor, ['mariadb', 'mysql'], true)) {
            throw new RuntimeException('GitLab invoice tests require DB_VENDOR=mysql or DB_VENDOR=mariadb.');
        }

        if (!is_string($jobId) || trim($jobId) === '') {
            throw new RuntimeException('GitLab invoice tests require CI_JOB_ID.');
        }

        if ($databaseHost !== 'db') {
            throw new RuntimeException('GitLab invoice tests require the isolated database service host "db".');
        }

        if ($databaseName !== 'quiqqer') {
            throw new RuntimeException('GitLab invoice tests require the disposable database "quiqqer".');
        }

        return self::MODE_CI_DATABASE;
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

    public static function getCiVendor(): string
    {
        if (!self::usesCiDatabase()) {
            throw new RuntimeException('The CI database vendor is unavailable outside GitLab CI.');
        }

        return (string)getenv('DB_VENDOR');
    }
}
