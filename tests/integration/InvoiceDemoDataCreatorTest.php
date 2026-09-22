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
use QUI;
use QUI\ERP\Accounting\Invoice\DemoData\InvoiceDemoDataCreator;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataDateRange;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\Interfaces\Users\User;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use Throwable;

class InvoiceDemoDataCreatorTest extends SqliteIntegrationTestCase
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
        parent::setUp();
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

    public function testCreatesTemporaryAndPostedInvoicesForCustomerDemoData(): void
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
                    new DateTimeImmutable('2022-02-28 23:59:59')
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

        self::assertCount(8, $CreatedDemoData);

        foreach ($CreatedDemoData as $DemoData) {
            $invoice = match ($DemoData->entityType) {
                'invoice_temporary' => Handler::getInstance()->getTemporaryInvoice($DemoData->entityUuid),
                'invoice' => Handler::getInstance()->getInvoice($DemoData->entityUuid),
                default => self::fail('Unexpected invoice entity type.')
            };
            $invoiceDate = new DateTimeImmutable((string)$invoice->getAttribute('date'));

            self::assertGreaterThanOrEqual(new DateTimeImmutable('2022-01-01 00:00:00'), $invoiceDate);
            self::assertLessThanOrEqual(new DateTimeImmutable('2022-02-28 23:59:59'), $invoiceDate);
        }

        $demoDataCollection = new DemoDataReferenceCollection([
            'quiqqer.invoice' => array_map(
                static fn (CreatedDemoData $demoData): DemoDataReference => new DemoDataReference(
                    'quiqqer.invoice',
                    $demoData->entityType,
                    $demoData->entityUuid,
                    $demoData->referenceKey,
                    []
                ),
                $CreatedDemoData
            )
        ]);

        $Creator->deleteDemoData($demoDataCollection);
        $this->invoiceUuids = [];

        foreach ($CreatedDemoData as $demoData) {
            try {
                match ($demoData->entityType) {
                    'invoice_temporary' => Handler::getInstance()->getTemporaryInvoice($demoData->entityUuid),
                    'invoice' => Handler::getInstance()->getInvoice($demoData->entityUuid),
                    default => self::fail('Unexpected invoice entity type.')
                };
                self::fail('The demo invoice was not deleted.');
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

        $Address = $User->addAddress([
            'firstname' => $type,
            'lastname' => 'Customer',
            'street_no' => 'Demo Street 1',
            'zip' => '12345',
            'city' => 'Demo City',
            'country' => 'DE'
        ], $SystemUser);
        $User->setAttribute('address', $Address->getUUID());
        $User->save($SystemUser);

        $this->customerUuids[] = $User->getUUID();

        return $User;
    }
}
