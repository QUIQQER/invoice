<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Payment;
use QUI\ERP\Enums\Payments\EN16931;

class PaymentUnitTest extends TestCase
{
    public function testEmptyPaymentUsesNeutralValues(): void
    {
        $Payment = new Payment();

        self::assertSame('', $Payment->getTitle());
        self::assertSame('', $Payment->getDescription());
        self::assertSame('', $Payment->getPaymentType());
        self::assertSame(EN16931::NOT_DEFINED, $Payment->getTypeCode());
    }

    public function testLocalizedValuesAndPaymentTypeAreReturned(): void
    {
        $Locale = QUI::getLocale();
        $language = $Locale->getCurrent();
        $Payment = new Payment([
            'title' => [$language => 'Invoice payment'],
            'description' => [$language => 'Pay later'],
            'payment_type' => 'invoice'
        ]);

        self::assertSame('Invoice payment', $Payment->getTitle($Locale));
        self::assertSame('Pay later', $Payment->getDescription($Locale));
        self::assertSame('invoice', $Payment->getPaymentType());
    }

    public function testInvoiceInformationIsEmptyWithoutConfiguredPayment(): void
    {
        $Invoice = $this->createMock(Invoice::class);

        self::assertSame('', (new Payment())->getInvoiceInformationText($Invoice));
    }
}
