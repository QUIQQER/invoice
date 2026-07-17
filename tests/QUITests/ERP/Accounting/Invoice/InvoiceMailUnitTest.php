<?php

namespace QUITests\ERP\Accounting\Invoice;

require_once __DIR__ . '/MailOutputFake.php';

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Output\Output as ErpOutput;
use ReflectionMethod;

#[PreserveGlobalState(false)]
#[RunTestsInSeparateProcesses]
class InvoiceMailUnitTest extends TestCase
{
    public function testSendToMapsInvoiceTypesWithoutSendingMail(): void
    {
        self::assertFalse(class_exists(ErpOutput::class, false));
        class_alias(MailOutputFake::class, ErpOutput::class);

        $types = [
            QUI\ERP\Constants::TYPE_INVOICE => 'Invoice',
            QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE => 'CreditNote',
            QUI\ERP\Constants::TYPE_INVOICE_CANCEL => 'Canceled',
            QUI\ERP\Constants::TYPE_INVOICE_STORNO => 'Canceled'
        ];

        foreach ($types as $invoiceType => $outputType) {
            $Invoice = $this->getMockBuilder(Invoice::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['getInvoiceType', 'getUUID'])
                ->getMock();
            $Invoice->method('getInvoiceType')->willReturn($invoiceType);
            $Invoice->method('getUUID')->willReturn('invoice-' . $invoiceType);

            $Invoice->sendTo('recipient@example.invalid', 'phpunit-template');

            self::assertSame([
                'invoice-' . $invoiceType,
                $outputType,
                null,
                null,
                'phpunit-template',
                'recipient@example.invalid'
            ], MailOutputFake::$calls[array_key_last(MailOutputFake::$calls)]);
        }
    }

    public function testCreationMailUsesCustomerEmailAndHonorsOptOut(): void
    {
        $Customer = new QUI\ERP\User([
            'uuid' => 'mail-customer',
            'email' => 'customer@example.invalid'
        ]);
        $Method = new ReflectionMethod(InvoiceTemporary::class, 'sendCreationMail');

        $Invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getCustomer', 'sendTo'])
            ->getMock();
        $Invoice->method('getCustomer')->willReturn($Customer);
        $Invoice->expects(self::once())
            ->method('sendTo')
            ->with('customer@example.invalid');

        $Draft = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();
        $Draft->method('getAttribute')->willReturn(null);
        $Method->invoke($Draft, $Invoice);

        $OptOutInvoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['sendTo'])
            ->getMock();
        $OptOutInvoice->expects(self::never())->method('sendTo');

        $OptOutDraft = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAttribute'])
            ->getMock();
        $OptOutDraft->method('getAttribute')->willReturn(1);
        $Method->invoke($OptOutDraft, $OptOutInvoice);
    }
}
