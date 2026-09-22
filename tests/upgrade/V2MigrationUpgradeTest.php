<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Upgrade;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ColumnDiff;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\StringType;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\System\Console\Tools\MigrationV2;
use QUI\Utils\Doctrine;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use Throwable;

class V2MigrationUpgradeTest extends SqliteIntegrationTestCase
{
    private ?string $customerUuid = null;
    private ?string $invoiceUuid = null;
    private ?string $temporaryInvoiceUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getInvoiceTables() as $table) {
            QUI::getDataBaseConnection()->executeStatement(
                'DELETE FROM ' . Doctrine::quoteIdentifier($table)
            );
        }
    }

    protected function tearDown(): void
    {
        $Connection = QUI::getDataBaseConnection();

        if ($this->invoiceUuid !== null) {
            $Connection->delete(Handler::getInstance()->invoiceTable(), ['hash' => $this->invoiceUuid]);
        }

        if ($this->temporaryInvoiceUuid !== null) {
            $Connection->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['hash' => $this->temporaryInvoiceUuid]
            );
        }

        QUI\Update::importDatabase(dirname(__DIR__, 2) . '/database.xml');
        OrderHandlerDouble::reset();

        if ($this->customerUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->customerUuid);
            } catch (Throwable) {
            }
        }

        parent::tearDown();
    }

    public function testMigratesLegacyReferencesInPostedAndTemporaryInvoicesRepeatably(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $username = 'invoice-v2-migration-' . uniqid();
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Migration',
            'lastname' => 'Customer'
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Migration',
            'lastname' => 'Customer',
            'street_no' => 'Legacy Street 2',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);
        $User->setAttribute('address', $Address->getUUID());
        $User->save($SystemUser);
        $userId = $User->getId();

        self::assertIsInt($userId);

        $PostedDraft = $this->createDraft($User, $Address, 'V2-POSTED');
        $Invoice = $PostedDraft->post($SystemUser);
        $this->invoiceUuid = $Invoice->getUUID();
        $TemporaryInvoice = $this->createDraft($User, $Address, 'V2-TEMPORARY');
        $TemporaryInvoice->save($SystemUser);
        $this->temporaryInvoiceUuid = $TemporaryInvoice->getUUID();
        $legacyOrderId = 7001;
        $orderUuid = QUI\Utils\Uuid::get();
        OrderHandlerDouble::setOrderUuid($legacyOrderId, $orderUuid);
        $invoiceTable = Handler::getInstance()->invoiceTable();
        $temporaryInvoiceTable = Handler::getInstance()->temporaryInvoiceTable();

        QUI::getDataBaseConnection()->update(
            $invoiceTable,
            [
                'customer_id' => $userId,
                'ordered_by' => $userId,
                'c_user' => $userId,
                'editor_id' => $userId,
                'order_id' => $legacyOrderId
            ],
            ['hash' => $this->invoiceUuid]
        );
        QUI::getDataBaseConnection()->update(
            $temporaryInvoiceTable,
            [
                'customer_id' => $userId,
                'invoice_address_id' => $Address->getId(),
                'delivery_address_id' => $Address->getId(),
                'ordered_by' => $userId,
                'c_user' => $userId,
                'editor_id' => $userId,
                'order_id' => $legacyOrderId
            ],
            ['hash' => $this->temporaryInvoiceUuid]
        );

        $this->changeColumnsToLegacyIntegers($invoiceTable, [
            'customer_id' => true,
            'ordered_by' => false,
            'c_user' => true,
            'editor_id' => false,
            'order_id' => false
        ]);
        $this->changeColumnsToLegacyIntegers($temporaryInvoiceTable, [
            'customer_id' => true,
            'invoice_address_id' => false,
            'delivery_address_id' => false,
            'ordered_by' => false,
            'c_user' => true,
            'editor_id' => false,
            'order_id' => false
        ]);

        $Console = $this->createMock(MigrationV2::class);
        $Console->expects(self::exactly(2))->method('writeLn')->with('- Migrate invoice');
        EventHandler::onQuiqqerMigrationV2($Console);
        EventHandler::onQuiqqerMigrationV2($Console);

        $postedData = $this->fetchInvoiceData($invoiceTable, $this->invoiceUuid);
        $temporaryData = $this->fetchInvoiceData($temporaryInvoiceTable, $this->temporaryInvoiceUuid);

        foreach (['customer_id', 'ordered_by', 'c_user', 'editor_id'] as $field) {
            self::assertSame($this->customerUuid, $postedData[$field]);
            self::assertSame($this->customerUuid, $temporaryData[$field]);
        }

        self::assertSame($Address->getUUID(), $temporaryData['invoice_address_id']);
        self::assertSame($Address->getUUID(), $temporaryData['delivery_address_id']);
        self::assertSame($orderUuid, $postedData['order_id']);
        self::assertSame($orderUuid, $temporaryData['order_id']);
        $this->assertMigratedColumnDefinitions($invoiceTable, [
            'customer_id' => true,
            'ordered_by' => false,
            'c_user' => true,
            'editor_id' => false,
            'order_id' => false
        ]);
        $this->assertMigratedColumnDefinitions($temporaryInvoiceTable, [
            'customer_id' => true,
            'invoice_address_id' => false,
            'delivery_address_id' => false,
            'ordered_by' => false,
            'c_user' => true,
            'editor_id' => false,
            'order_id' => false
        ]);
    }

    private function createDraft(
        QUI\Interfaces\Users\User $User,
        QUI\Users\Address $Address,
        string $articleNumber
    ): InvoiceTemporary {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Draft = Factory::getInstance()->createInvoice($SystemUser, QUI\Utils\Uuid::get());
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->setDeliveryAddress([
            'firstname' => 'Migration',
            'lastname' => 'Customer',
            'street_no' => 'Legacy Street 2',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => $articleNumber,
            'title' => 'V2 migration fixture',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));

        return $Draft;
    }

    /**
     * @param array<string, bool> $columns
     */
    private function changeColumnsToLegacyIntegers(string $table, array $columns): void
    {
        $SchemaManager = QUI::getSchemaManager();
        $Table = $SchemaManager->introspectTable($table);
        $changedColumns = [];

        foreach ($columns as $columnName => $notNull) {
            $CurrentColumn = $Table->getColumn($columnName);
            $LegacyColumn = new Column(
                $columnName,
                Type::getType(Types::INTEGER),
                ['notnull' => $notNull, 'default' => null]
            );
            $changedColumns[$columnName] = new ColumnDiff($CurrentColumn, $LegacyColumn);
        }

        $SchemaManager->alterTable(new TableDiff($Table, changedColumns: $changedColumns));
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchInvoiceData(string $table, string $hash): array
    {
        $result = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('*')
            ->from(Doctrine::quoteIdentifier($table))
            ->where('hash = :hash')
            ->setParameter('hash', $hash)
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($result);

        return $result;
    }

    /**
     * @param array<string, bool> $columns
     */
    private function assertMigratedColumnDefinitions(string $table, array $columns): void
    {
        $Table = QUI::getSchemaManager()->introspectTable($table);

        foreach ($columns as $columnName => $notNull) {
            $Column = $Table->getColumn($columnName);
            self::assertInstanceOf(StringType::class, $Column->getType());
            self::assertSame(50, $Column->getLength());
            self::assertSame($notNull, $Column->getNotnull());
        }
    }

    /**
     * @return list<string>
     */
    private function getInvoiceTables(): array
    {
        return [
            Handler::getInstance()->invoiceTable(),
            Handler::getInstance()->temporaryInvoiceTable()
        ];
    }
}
