<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\Utils\Singleton;
use ReflectionProperty;

class InvoiceTransactionUnitTest extends TestCase
{
    public function testStatusChangeUpdatesEachLinkedInvoiceOnceAndSkipsUnknownHashes(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->expects(self::once())->method('calculatePayments');
        $Draft = $this->createMock(InvoiceTemporary::class);
        $Draft->expects(self::once())->method('calculatePayments');

        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getHash')->willReturn('invoice');
        $Transaction->method('getLinkedHashes')->willReturn([
            'invoice',
            '',
            'missing-entity',
            'draft',
            'draft'
        ]);

        $Handler = $this->createMock(Handler::class);
        $Handler->expects(self::exactly(3))->method('getInvoiceByHash')->willReturnCallback(
            static function (string $hash) use ($Invoice, $Draft): Invoice | InvoiceTemporary {
                return match ($hash) {
                    'invoice' => $Invoice,
                    'draft' => $Draft,
                    'missing-entity' => throw new QUI\Exception('Entity is not an invoice'),
                    default => throw new \LogicException('Unexpected invoice lookup: ' . $hash)
                };
            }
        );

        $Instances = new ReflectionProperty(Singleton::class, 'instances');
        $originalInstances = $Instances->getValue();
        $instances = $originalInstances;
        $instances[Handler::class] = $Handler;
        $Instances->setValue(null, $instances);

        try {
            EventHandler::onTransactionStatusChange($Transaction);
        } finally {
            $Instances->setValue(null, $originalInstances);
        }
    }

    public static function guardedInvoiceTypeProvider(): array
    {
        return [
            'credit note' => [QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE],
            'cancellation' => [QUI\ERP\Constants::TYPE_INVOICE_CANCEL],
            'reversal' => [QUI\ERP\Constants::TYPE_INVOICE_REVERSAL]
        ];
    }

    #[DataProvider('guardedInvoiceTypeProvider')]
    public function testTransactionIsIgnoredForNonPayableInvoiceTypes(int $invoiceType): void
    {
        $Invoice = $this->createInvoiceMock($invoiceType, 1);
        $Invoice->expects(self::never())->method('addHistory');

        $Invoice->addTransaction($this->createTransactionMock(10, '2026-07-17 12:00:00'));
    }

    public function testZeroAmountAndDuplicateTransactionsAreIgnored(): void
    {
        $ZeroInvoice = $this->createInvoiceMock(QUI\ERP\Constants::TYPE_INVOICE, 1);
        $ZeroInvoice->expects(self::never())->method('addHistory');
        $ZeroInvoice->addTransaction($this->createTransactionMock(0, '2026-07-17 12:00:00'));

        $DuplicateInvoice = $this->createInvoiceMock(QUI\ERP\Constants::TYPE_INVOICE, 1);
        $DuplicateInvoice->setAttribute('paid_data', json_encode([['txid' => 'phpunit-transaction']]));
        $DuplicateInvoice->expects(self::never())->method('addHistory');
        $DuplicateInvoice->addTransaction($this->createTransactionMock(5, '2026-07-17 12:00:00'));
    }

    public function testInvalidTransactionDateFallsBackToCurrentTimestamp(): void
    {
        $before = time();
        $Invoice = $this->createInvoiceMock(QUI\ERP\Constants::TYPE_INVOICE, 2);
        $Invoice->expects(self::once())->method('addHistory');

        $Invoice->addTransaction($this->createTransactionMock(5, 'not-a-date'));

        self::assertGreaterThanOrEqual($before, $Invoice->getAttribute('paid_date'));
        self::assertLessThanOrEqual(time(), $Invoice->getAttribute('paid_date'));
    }

    public function testTemporaryInvoicePersistsPaymentCalculation(): void
    {
        $Invoice = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUUID', 'getInvoiceType', 'calculatePayments', 'addHistory'])
            ->getMock();
        $Invoice->method('getUUID')->willReturn('phpunit-invoice');
        $Invoice->method('getInvoiceType')->willReturn(QUI\ERP\Constants::TYPE_INVOICE);
        $Invoice->expects(self::exactly(2))->method('calculatePayments');
        $Invoice->expects(self::once())->method('addHistory');
        $Invoice->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);

        $Invoice->addTransaction($this->createTransactionMock(10, '2026-07-17 12:00:00'));
    }

    private function createInvoiceMock(int $invoiceType, int $calculationCalls): Invoice
    {
        $Invoice = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUUID', 'getInvoiceType', 'calculatePayments', 'addHistory'])
            ->getMock();
        $Invoice->method('getUUID')->willReturn('phpunit-invoice');
        $Invoice->method('getInvoiceType')->willReturn($invoiceType);
        $Invoice->expects(self::exactly($calculationCalls))->method('calculatePayments');
        $Invoice->setAttribute('paid_status', QUI\ERP\Constants::PAYMENT_STATUS_OPEN);

        return $Invoice;
    }

    private function createTransactionMock(float $amount, string $date): Transaction
    {
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getHash')->willReturn('phpunit-invoice');
        $Transaction->method('getAmount')->willReturn($amount);
        $Transaction->method('getDate')->willReturn($date);
        $Transaction->method('getTxId')->willReturn('phpunit-transaction');

        return $Transaction;
    }
}
