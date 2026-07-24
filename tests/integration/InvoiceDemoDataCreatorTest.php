<?php

/**
 * This file is part of the QUIQQER project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\DemoData\InvoiceDemoDataCreator;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataDateRange;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\Interfaces\Users\User;
use Throwable;

class InvoiceDemoDataCreatorTest extends TestCase
{
    /**
     * @var string[]
     */
    private array $customerUuids = [];

    /**
     * @var string[]
     */
    private array $invoiceUuids = [];

    protected function setUp(): void
    {
        try {
            QUI::getDataBase()->fetchOne('SELECT 1');
        } catch (Throwable) {
            $this->markTestSkipped('A configured QUIQQER database is required for this integration test.');
        }
    }

    protected function tearDown(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();

        foreach ($this->invoiceUuids as $invoiceUuid) {
            try {
                Handler::getInstance()->delete($invoiceUuid, $SystemUser);
            } catch (Throwable) {
                // The invoice may already have been deleted by the tested code.
            }
        }

        foreach ($this->customerUuids as $customerUuid) {
            try {
                QUI::getUsers()->get($customerUuid)->delete($SystemUser);
            } catch (Throwable) {
                // The user may already have been deleted during cleanup.
            }
        }

        parent::tearDown();
    }

    public function testCreatesAndDeletesTemporaryInvoicesForCustomerDemoData(): void
    {
        $PrivateCustomer = $this->createCustomer('Private');
        $BusinessCustomer = $this->createCustomer('Business');

        $Context = new DemoDataCreationContext(
            new DemoDataReferenceCollection([
                'quiqqer.customer' => [
                    new DemoDataReference(
                        'quiqqer.customer',
                        'customer',
                        $PrivateCustomer->getUUID(),
                        'private_customer',
                        []
                    ),
                    new DemoDataReference(
                        'quiqqer.customer',
                        'customer',
                        $BusinessCustomer->getUUID(),
                        'business_customer',
                        []
                    )
                ]
            ]),
            [
                new DemoDataDateRange(
                    new DateTimeImmutable('2022-01-01 00:00:00'),
                    new DateTimeImmutable('2023-12-31 23:59:59')
                )
            ]
        );

        $Creator = new InvoiceDemoDataCreator();

        self::assertSame(['quiqqer.customer'], $Creator->getDependencies());

        $CreatedDemoData = $Creator->createDemoData($Context)->all();
        $this->invoiceUuids = array_map(
            static fn (CreatedDemoData $DemoData): string => $DemoData->entityUuid,
            $CreatedDemoData
        );

        self::assertCount(2, $CreatedDemoData);

        foreach ($CreatedDemoData as $DemoData) {
            self::assertSame('invoice_temporary', $DemoData->entityType);
            self::assertNotEmpty(Handler::getInstance()->getTemporaryInvoice($DemoData->entityUuid));
        }

        $DemoDataCollection = new DemoDataReferenceCollection([
            'quiqqer.invoice' => array_map(
                static fn (CreatedDemoData $DemoData): DemoDataReference => new DemoDataReference(
                    'quiqqer.invoice',
                    $DemoData->entityType,
                    $DemoData->entityUuid,
                    $DemoData->referenceKey,
                    []
                ),
                $CreatedDemoData
            )
        ]);

        $Creator->deleteDemoData($DemoDataCollection);
        $this->invoiceUuids = [];

        foreach ($CreatedDemoData as $DemoData) {
            try {
                Handler::getInstance()->getTemporaryInvoice($DemoData->entityUuid);
                self::fail('The temporary invoice was not deleted.');
            } catch (\QUI\Exception) {
                self::assertTrue(true);
            }
        }
    }

    private function createCustomer(string $type): User
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $User = QUI::getUsers()->createChildWithAttributes([
            'name' => 'invoice-demo-data-' . strtolower($type) . '-' . uniqid(),
            'username' => 'invoice-demo-data-' . strtolower($type) . '-' . uniqid(),
            'firstname' => $type,
            'lastname' => 'Customer'
        ], $SystemUser);

        $User->addAddress([
            'firstname' => $type,
            'lastname' => 'Customer',
            'street' => 'Demo Street',
            'street_no' => '1',
            'zip' => '12345',
            'city' => 'Demo City',
            'country' => 'DE'
        ], $SystemUser);

        $this->customerUuids[] = $User->getUUID();

        return $User;
    }
}
