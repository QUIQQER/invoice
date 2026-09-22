<?php

if (!defined('QUIQQER_SYSTEM')) {
    define('QUIQQER_SYSTEM', true);
}

if (!defined('QUIQQER_AJAX')) {
    define('QUIQQER_AJAX', true);
}

require_once __DIR__ . '/QUITests/ERP/Accounting/Invoice/DatabaseEnvironment.php';

putenv("QUIQQER_OTHER_AUTOLOADERS=KEEP");

require_once __DIR__ . '/stubs/Mcp/Server/Builder.php';
require_once __DIR__ . '/stubs/Mcp/Schema/Result/CallToolResult.php';
require_once __DIR__ . '/stubs/QUI/AI/MCP/ProviderInterface.php';
require_once __DIR__ . '/stubs/QUI/AI/MCP/Server.php';
require_once __DIR__ . '/stubs/QUI/AI/MCP/ToolHelper.php';
require_once __DIR__ . '/stubs/QUI/FrontendUsers/Controls/Profile/ControlInterface.php';
require_once __DIR__ . '/stubs/QUI/ERP/Shipping/Api/ShippingInterface.php';
require_once __DIR__ . '/stubs/QUI/ERP/Shipping/Types/ShippingEntry.php';
require_once __DIR__ . '/stubs/QUI/ERP/Shipping/Shipping.php';

$systemRoot = dirname(__DIR__, 4);
$coreRoot = $systemRoot . '/packages/quiqqer/core';

if (!defined('ETC_DIR')) {
    define('ETC_DIR', $systemRoot . '/etc/');
}

require_once $coreRoot . '/src/autoload.php';
require_once $coreRoot . '/src/minimalHeader.php';

$phpunitBootstrapConnection = null;

if (!QUITests\ERP\Accounting\Invoice\DatabaseEnvironment::usesCiDatabase()) {
    $phpunitBootstrapConnection = Doctrine\DBAL\DriverManager::getConnection([
        'driver' => 'pdo_sqlite',
        'memory' => true
    ]);

    (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(
        null,
        $phpunitBootstrapConnection
    );

    register_shutdown_function(static function () use ($phpunitBootstrapConnection): void {
        $phpunitBootstrapConnection->close();
    });
}

if (file_exists(__DIR__ . '/../../../autoload.php')) {
    require_once __DIR__ . '/../../../autoload.php';
}

$phpunitIsolatedConfigPaths = [];
$phpunitOriginalConfigContents = [];

register_shutdown_function(static function () use (
    &$phpunitIsolatedConfigPaths,
    &$phpunitOriginalConfigContents
): void {
    foreach ($phpunitOriginalConfigContents as $originalConfigPath => $originalConfigContents) {
        if (file_get_contents($originalConfigPath) !== $originalConfigContents) {
            file_put_contents($originalConfigPath, $originalConfigContents);
        }
    }

    foreach ($phpunitIsolatedConfigPaths as $isolatedConfigPath) {
        if (file_exists($isolatedConfigPath)) {
            unlink($isolatedConfigPath);
        }
    }
});

foreach (['quiqqer/invoice', 'quiqqer/erp', 'quiqqer/tax'] as $packageName) {
    $Package = QUI::getPackage($packageName);

    if ($Package->getConfig() === null) {
        throw new RuntimeException('Package configuration is not available for PHPUnit.');
    }

    $configPathProperty = new ReflectionProperty($Package, 'configPath');
    $configProperty = new ReflectionProperty($Package, 'Config');
    $originalConfigPath = $configPathProperty->getValue($Package);
    $isolatedConfigPath = tempnam(sys_get_temp_dir(), 'quiqqer-phpunit-config-');

    if (!is_string($originalConfigPath) || $isolatedConfigPath === false) {
        throw new RuntimeException('Could not prepare isolated package configuration.');
    }

    $originalConfigContents = file_get_contents($originalConfigPath);

    if ($originalConfigContents === false) {
        throw new RuntimeException('Could not read package configuration for PHPUnit.');
    }

    if (file_put_contents($isolatedConfigPath, $originalConfigContents) === false) {
        throw new RuntimeException('Could not copy package configuration for PHPUnit.');
    }

    $phpunitOriginalConfigContents[$originalConfigPath] = $originalConfigContents;
    $configPathProperty->setValue($Package, $isolatedConfigPath);
    $configProperty->setValue($Package, null);
    $phpunitIsolatedConfigPaths[] = $isolatedConfigPath;
}

require_once __DIR__ . '/QUITests/ERP/Accounting/Invoice/SqliteIntegrationTestCase.php';

require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/CreatedDemoData.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/CreatedDemoDataCollection.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataReference.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataReferenceCollection.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataDateRange.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/DTO/DemoDataCreationContext.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/Exception/DemoDataException.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/Contract/DemoDataCreatorInterface.php';
require_once __DIR__ . '/stubs/QUI/ERP/DemoData/Contract/DemoDataProviderInterface.php';
require_once __DIR__ . '/stubs/QUI/REST/ProviderInterface.php';
require_once __DIR__ . '/stubs/QUI/REST/ResponseStream.php';
require_once __DIR__ . '/stubs/QUI/REST/Response.php';
require_once __DIR__ . '/stubs/QUI/REST/Utils/RequestUtils.php';

QUI::$Rights = null;
QUI::$Users = new QUI\Users\Manager();
QUI::$ProjectManager = null;
(new ReflectionProperty(QUI\Utils\Singleton::class, 'instances'))->setValue(null, []);
QUI\Permissions\Permission::setUser(QUI::getUsers()->getSystemUser());

foreach (['currencies', 'Default', 'RuntimeCurrency'] as $property) {
    (new ReflectionProperty(QUI\ERP\Currency\Handler::class, $property))->setValue(
        null,
        $property === 'currencies' ? [] : null
    );
}

foreach (['countries', 'DefaultCountry'] as $property) {
    (new ReflectionProperty(QUI\Countries\Manager::class, $property))->setValue(
        null,
        $property === 'countries' ? [] : null
    );
}

QUI::getSession()->set('country', 'DE');
(new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(
    QUI::getUsers(),
    QUI::getUsers()->getSystemUser()
);

if ($phpunitBootstrapConnection instanceof Doctrine\DBAL\Connection) {
    QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase::initializeConnection(
        $phpunitBootstrapConnection
    );
}
