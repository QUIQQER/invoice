<?php

namespace QUITests\ERP\Accounting\Invoice;

use DateTime;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\PaymentReceiver;
use ReflectionClass;
use ReflectionProperty;

class PaymentReceiverUnitTest extends TestCase
{
    public function testMissingDatesUseNeutralFallbacks(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getAttribute')->willReturn(null);
        $Receiver = $this->createReceiver($Invoice);

        self::assertInstanceOf(DateTime::class, $Receiver->getDate());
        self::assertFalse($Receiver->getDueDate());
    }

    public function testUnknownPaymentMethodReturnsFalse(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getAttribute')->with('payment_method')->willReturn('phpunit-missing-payment');

        self::assertFalse($this->createReceiver($Invoice)->getPaymentMethod());
    }

    private function createReceiver(Invoice $Invoice): PaymentReceiver
    {
        $Receiver = (new ReflectionClass(PaymentReceiver::class))->newInstanceWithoutConstructor();
        $Property = new ReflectionProperty($Receiver, 'Invoice');
        $Property->setAccessible(true);
        $Property->setValue($Receiver, $Invoice);

        return $Receiver;
    }
}
