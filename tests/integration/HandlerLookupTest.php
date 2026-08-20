<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use QUI;
use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;

class HandlerLookupTest extends SqliteIntegrationTestCase
{
    private ?string $temporaryInvoiceUuid = null;

    protected function tearDown(): void
    {
        if ($this->temporaryInvoiceUuid !== null) {
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['hash' => $this->temporaryInvoiceUuid]
            );
        }

        parent::tearDown();
    }

    public function testRejectsNonnumericIdentifierAfterTemporaryInvoiceHashMiss(): void
    {
        $Draft = Factory::getInstance()->createInvoice(QUI::getUsers()->getSystemUser());
        $this->temporaryInvoiceUuid = $Draft->getUUID();
        $invalidIdentifier = $Draft->getId() . '-not-an-invoice-hash';

        self::assertSame(
            $Draft->getUUID(),
            Handler::getInstance()->getTemporaryInvoice($Draft->getUUID())->getUUID()
        );

        $this->expectException(Exception::class);
        $this->expectExceptionCode(404);

        Handler::getInstance()->getTemporaryInvoice($invalidIdentifier);
    }
}
