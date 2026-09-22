<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Upgrade;

use QUI;
use QUI\Config;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Factory as InvoiceFactory;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\ERP\Accounting\Invoice\Settings;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use ReflectionProperty;
use Throwable;

class PackageSetupUpgradeTest extends SqliteIntegrationTestCase
{
    private Config $invoiceConfig;

    /** @var array<string, mixed> */
    private array $originalInvoiceConfigState;

    private ?string $customerUuid = null;
    private ?string $invoiceUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

        $Config = Settings::getConfig();
        $configState = (new ReflectionProperty($Config, 'iniParsedArray'))->getValue($Config);

        if (!is_array($configState)) {
            throw new \RuntimeException('The invoice configuration has an invalid state.');
        }

        $this->invoiceConfig = $Config;
        $this->originalInvoiceConfigState = $configState;
    }

    protected function tearDown(): void
    {
        if ($this->invoiceUuid !== null) {
            QUI::getDataBaseConnection()->delete(
                InvoiceHandler::getInstance()->invoiceTable(),
                ['hash' => $this->invoiceUuid]
            );
        }

        if ($this->customerUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->customerUuid);
            } catch (Throwable) {
            }
        }

        (new ReflectionProperty($this->invoiceConfig, 'iniParsedArray'))->setValue(
            $this->invoiceConfig,
            $this->originalInvoiceConfigState
        );
        $this->invoiceConfig->save();
        ProcessingStatusHandler::getInstance()->clearCache();

        parent::tearDown();
    }

    public function testPackageSetupCreatesDefaultProcessingStatusesAndIsRepeatable(): void
    {
        $this->invoiceConfig->setSection('processing_status', []);
        $this->invoiceConfig->save();
        ProcessingStatusHandler::getInstance()->clearCache();
        $Package = QUI::getPackage('quiqqer/invoice');

        EventHandler::onPackageSetup($Package);
        $firstSetup = ProcessingStatusHandler::getInstance()->getList();
        EventHandler::onPackageSetup($Package);
        $secondSetup = ProcessingStatusHandler::getInstance()->getList();

        self::assertSame([1, 2, 3], array_keys($firstSetup));
        self::assertSame($firstSetup, $secondSetup);
        self::assertSame('#ff8c00', $this->getStatusColor($firstSetup[1]));
        self::assertSame('#9370db', $this->getStatusColor($firstSetup[2]));
        self::assertSame('#228b22', $this->getStatusColor($firstSetup[3]));
    }

    public function testPackageSetupPatchesMissingPrefixedInvoiceNumberOnce(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $username = 'invoice-upgrade-prefix-' . uniqid();
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Upgrade',
            'lastname' => 'Customer'
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Upgrade',
            'lastname' => 'Customer',
            'street_no' => 'Migration Street 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);
        $User->setAttribute('address', $Address->getUUID());
        $User->save($SystemUser);

        $Draft = InvoiceFactory::getInstance()->createInvoice($SystemUser, QUI\Utils\Uuid::get());
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'UPGRADE-PREFIX',
            'title' => 'Prefix patch fixture',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Invoice = $Draft->post($SystemUser);
        $this->invoiceUuid = $Invoice->getUUID();
        $invoiceTable = InvoiceHandler::getInstance()->invoiceTable();

        QUI::getDataBaseConnection()->update(
            $invoiceTable,
            ['id_prefix' => 'LEGACY-', 'id_with_prefix' => null],
            ['hash' => $this->invoiceUuid]
        );
        $this->invoiceConfig->del('patch', 'id_with_prefix');
        $this->invoiceConfig->save();

        EventHandler::onPackageSetup(QUI::getPackage('quiqqer/invoice'));
        EventHandler::onPackageSetup(QUI::getPackage('quiqqer/invoice'));

        $storedNumber = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('id_with_prefix')
            ->from($invoiceTable)
            ->where('hash = :hash')
            ->setParameter('hash', $this->invoiceUuid)
            ->executeQuery()
            ->fetchOne();

        self::assertSame('LEGACY-' . $Invoice->getId(), $storedNumber);
        self::assertSame(1, (int)$this->invoiceConfig->getValue('patch', 'id_with_prefix'));
    }

    private function getStatusColor(string $statusData): string
    {
        $data = json_decode($statusData, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($data);
        self::assertArrayHasKey('color', $data);
        self::assertIsString($data['color']);

        return $data['color'];
    }
}
