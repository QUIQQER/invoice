<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Countries\Manager as CountriesManager;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Manager as PermissionManager;
use QUI\Permissions\Permission;
use QUI\Update;
use QUI\Utils\Singleton;
use ReflectionProperty;

abstract class SqliteIntegrationTestCase extends TestCase
{
    protected Connection $connection;

    private Connection $originalConnection;
    private ?PermissionManager $originalPermissionManager;
    private mixed $originalPermissionUser;
    private mixed $originalUsersManager;
    private mixed $originalProjectManager;
    private mixed $originalSingletonInstances;

    /** @var array<string, mixed> */
    private array $originalCurrencyState;

    /** @var array<string, mixed> */
    private array $originalCountriesState;

    private mixed $originalAvailableLanguages;

    private mixed $originalSessionCountry;

    private string $originalLocaleCurrent;

    private mixed $originalLocaleTemporaryCurrent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = QUI::getDataBaseConnection();
        $this->originalPermissionManager = QUI::$Rights;
        $this->originalPermissionUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();
        $this->originalUsersManager = QUI::$Users;
        $this->originalProjectManager = QUI::$ProjectManager;
        $this->originalSingletonInstances = (new ReflectionProperty(Singleton::class, 'instances'))->getValue();
        $this->originalCurrencyState = $this->getStaticState(
            CurrencyHandler::class,
            ['currencies', 'Default', 'RuntimeCurrency']
        );
        $this->originalCountriesState = $this->getStaticState(
            CountriesManager::class,
            ['countries', 'DefaultCountry']
        );
        $this->originalAvailableLanguages = (new ReflectionProperty(
            QUI\Translator::class,
            'availableLanguages'
        ))->getValue();
        $this->originalSessionCountry = QUI::getSession()->get('country');
        $this->originalLocaleCurrent = QUI::getLocale()->getCurrent();
        $this->originalLocaleTemporaryCurrent = (new ReflectionProperty(
            QUI\Locale::class,
            'tempCurrent'
        ))->getValue(QUI::getLocale());

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true
        ]);

        $this->setConnection($this->connection);
        QUI::$Rights = null;
        QUI::$Users = new QUI\Users\Manager();
        QUI::$ProjectManager = null;
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(null, []);
        Permission::setUser(QUI::getUsers()->getSystemUser());
        $this->setStaticState(CurrencyHandler::class, [
            'currencies' => [],
            'Default' => null,
            'RuntimeCurrency' => null
        ]);
        $this->setStaticState(CountriesManager::class, [
            'countries' => [],
            'DefaultCountry' => null
        ]);
        QUI::getSession()->set('country', 'DE');
        $this->replaceSessionUser(QUI::getUsers()->getSystemUser());

        self::initializeConnection($this->connection);
    }

    protected function tearDown(): void
    {
        $this->setConnection($this->originalConnection);
        QUI::$Rights = $this->originalPermissionManager;
        QUI::$Users = $this->originalUsersManager;
        QUI::$ProjectManager = $this->originalProjectManager;
        (new ReflectionProperty(Permission::class, 'User'))->setValue(
            null,
            $this->originalPermissionUser
        );
        (new ReflectionProperty(Singleton::class, 'instances'))->setValue(
            null,
            $this->originalSingletonInstances
        );
        $this->setStaticState(CurrencyHandler::class, $this->originalCurrencyState);
        $this->setStaticState(CountriesManager::class, $this->originalCountriesState);
        (new ReflectionProperty(QUI\Translator::class, 'availableLanguages'))->setValue(
            null,
            $this->originalAvailableLanguages
        );

        if ($this->originalSessionCountry === false) {
            QUI::getSession()->del('country');
        } else {
            QUI::getSession()->set('country', $this->originalSessionCountry);
        }

        QUI::getLocale()->setCurrent($this->originalLocaleCurrent);
        (new ReflectionProperty(QUI\Locale::class, 'tempCurrent'))->setValue(
            QUI::getLocale(),
            $this->originalLocaleTemporaryCurrent
        );

        $this->connection->close();

        parent::tearDown();
    }

    protected function replaceSessionUser(User $User): ?User
    {
        $Users = QUI::getUsers();
        $Session = new ReflectionProperty($Users, 'Session');
        $previousUser = $Session->getValue($Users);
        $Session->setValue($Users, $User);

        return $previousUser instanceof User ? $previousUser : null;
    }

    public static function initializeConnection(Connection $connection): void
    {
        foreach (
            [
                CMS_DIR . 'packages/quiqqer/core/database.xml',
                OPT_DIR . 'quiqqer/translator/database.xml',
                OPT_DIR . 'quiqqer/customer/database.xml',
                OPT_DIR . 'quiqqer/currency/database.xml',
                OPT_DIR . 'quiqqer/areas/database.xml',
                OPT_DIR . 'quiqqer/tax/database.xml',
                OPT_DIR . 'quiqqer/payments/database.xml',
                OPT_DIR . 'quiqqer/payment-transactions/database.xml',
                OPT_DIR . 'quiqqer/erp/database.xml',
                OPT_DIR . 'quiqqer/order/database.xml',
                dirname(__DIR__, 5) . '/database.xml'
            ] as $schema
        ) {
            Update::importDatabase($schema);
        }

        // Older translator versions use MySQL-specific SHOW COLUMNS when their language cache is cold.
        (new ReflectionProperty(QUI\Translator::class, 'availableLanguages'))->setValue(null, ['de']);

        foreach (
            [
                'quiqqer/core' => CMS_DIR . 'packages/quiqqer/core/permissions.xml',
                'quiqqer/currency' => OPT_DIR . 'quiqqer/currency/permissions.xml',
                'quiqqer/erp' => OPT_DIR . 'quiqqer/erp/permissions.xml',
                'quiqqer/customer' => OPT_DIR . 'quiqqer/customer/permissions.xml',
                'quiqqer/invoice' => dirname(__DIR__, 5) . '/permissions.xml'
            ] as $package => $permissions
        ) {
            Update::importPermissions($permissions, $package);
        }

        self::createCountriesTable($connection);
        QUI::getProjectManager()->getStandard()->getMedia()->setup();

        $connection->insert(QUI\Users\Manager::table(), [
            'id' => 10,
            'uuid' => 'sqlite-reserved-user-id',
            'username' => 'sqlite-reserved-user-id'
        ]);
        $connection->insert(QUI\Users\Manager::table(), [
            'id' => 11,
            'uuid' => 'sqlite-super-user',
            'username' => 'sqlite-super-user',
            'active' => 1,
            'su' => 1
        ]);
        $connection->insert(CurrencyHandler::table(), [
            'currency' => 'EUR',
            'rate' => 1,
            'autoupdate' => 0,
            'precision' => 2,
            'type' => CurrencyHandler::CURRENCY_TYPE_DEFAULT,
            'customData' => null
        ]);
        $connection->insert(QUI::getDBTableName('areas'), [
            'id' => 1,
            'countries' => 'DE',
            'data' => '{}'
        ]);
        $connection->insert(CountriesManager::getDataBaseTableName(), [
            'countries_name' => 'Germany',
            'countries_iso_code_2' => 'DE',
            'countries_iso_code_3' => 'DEU',
            'numeric_code' => '276',
            'language' => 'de',
            'languages' => '["de"]',
            'currency' => 'EUR',
            'active' => 1
        ]);
    }

    private static function createCountriesTable(Connection $connection): void
    {
        $Table = new Table(CountriesManager::getDataBaseTableName());
        $Table->addColumn('countries_id', 'integer', ['autoincrement' => true]);
        $Table->addColumn('countries_name', 'string', ['length' => 64]);
        $Table->addColumn('countries_iso_code_2', 'string', ['length' => 2]);
        $Table->addColumn('countries_iso_code_3', 'string', ['length' => 3]);
        $Table->addColumn('numeric_code', 'string', ['length' => 4]);
        $Table->addColumn('language', 'string', ['length' => 3]);
        $Table->addColumn('languages', 'text');
        $Table->addColumn('currency', 'string', ['length' => 3]);
        $Table->addColumn('active', 'smallint', ['default' => 1]);
        $Table->setPrimaryKey(['countries_id']);
        $connection->createSchemaManager()->createTable($Table);
    }

    private function setConnection(Connection $Connection): void
    {
        (new ReflectionProperty(QUI::class, 'QueryBuilder'))->setValue(null, $Connection);
    }

    /**
     * @param class-string $className
     * @param list<string> $properties
     * @return array<string, mixed>
     */
    private function getStaticState(string $className, array $properties): array
    {
        $state = [];

        foreach ($properties as $property) {
            $state[$property] = (new ReflectionProperty($className, $property))->getValue();
        }

        return $state;
    }

    /**
     * @param class-string $className
     * @param array<string, mixed> $state
     */
    private function setStaticState(string $className, array $state): void
    {
        foreach ($state as $property => $value) {
            (new ReflectionProperty($className, $property))->setValue(null, $value);
        }
    }
}
