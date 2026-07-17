<?php

namespace QUITests\ERP\Accounting\Invoice\Utils;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

class InvoiceDueDateUnitTest extends TestCase
{
    public function testDueDateSupportsDraftAndPostedInvoice(): void
    {
        $Draft = $this->createMock(InvoiceTemporary::class);
        $Draft->method('getAttribute')->with('time_for_payment')->willReturn(5);

        self::assertGreaterThan(time(), InvoiceUtils::getInvoiceTimeForPaymentDate($Draft));

        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getAttribute')->with('time_for_payment')->willReturn('2026-07-31');

        self::assertSame(strtotime('2026-07-31'), InvoiceUtils::getInvoiceTimeForPaymentDate($Invoice));
    }
}
