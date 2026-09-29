<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Payments\Methods\Cash\Payment as CashPayment;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Accounting\Payments\Types\Factory as PaymentFactory;
use QUI\ERP\Constants;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;

class InvoicePaymentMethodChangeTest extends SqliteIntegrationTestCase
{
    public function testPaymentMethodChangesOnInvoicesWithTransactions(): void
    {
        $user = QUI::getUsers()->getSystemUser();
        $paymentMethod = PaymentFactory::getInstance()->createChild([
            'payment_type' => CashPayment::class,
            'active' => 1
        ]);
        $invoiceHash = QUI\Utils\Uuid::get();
        $transactionIds = [];

        try {
            $this->insertInvoice($invoiceHash, Constants::PAYMENT_STATUS_PART);
            $transaction = TransactionFactory::createPaymentTransaction(
                5,
                QUI\ERP\Defaults::getCurrency(),
                $invoiceHash,
                'existing-payment',
                [],
                $user
            );
            $transactionIds[] = $transaction->getTxId();

            $invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);
            $invoice->changePaymentMethod(
                $paymentMethod->getId(),
                'Teilzahlung wurde überprüft <script>alert(1)</script>'
            );

            $reloaded = Handler::getInstance()->getInvoiceByHash($invoiceHash);
            self::assertSame((string)$paymentMethod->getId(), (string)$reloaded->getAttribute('payment_method'));
            self::assertSame(
                $paymentMethod->getId(),
                (int)json_decode($reloaded->getAttribute('payment_method_data'), true)['id']
            );
            self::assertSame(
                CashPayment::class,
                json_decode($reloaded->getAttribute('payment_method_data'), true)['payment_type']
            );
            self::assertSame(Constants::PAYMENT_STATUS_PART, (int)$reloaded->getAttribute('paid_status'));
            self::assertSame('Existing transaction text', $reloaded->getAttribute('transaction_invoice_text'));
            self::assertSame('Previous payment text', $reloaded->getCustomDataEntry('InvoiceInformationText'));
            self::assertStringContainsString(
                'Teilzahlung wurde überprüft',
                json_encode($reloaded->getHistory()->toArray(), JSON_UNESCAPED_UNICODE)
            );
            self::assertStringContainsString(
                '&lt;script&gt;alert(1)&lt;/script&gt;',
                $reloaded->getHistory()->toArray()[0]['message']
            );
            self::assertSame(
                $transactionIds[0],
                TransactionHandler::getInstance()->getTransactionsByHash($invoiceHash)[0]->getTxId()
            );

            $secondTransaction = TransactionFactory::createPaymentTransaction(
                5,
                QUI\ERP\Defaults::getCurrency(),
                $invoiceHash,
                'existing-payment',
                [],
                $user
            );
            $transactionIds[] = $secondTransaction->getTxId();

            QUI::getDataBaseConnection()->update(
                Handler::getInstance()->invoiceTable(),
                [
                    'paid_status' => Constants::PAYMENT_STATUS_PAID,
                    'payment_method' => -1,
                    'payment_method_data' => json_encode(['id' => -1, 'title' => ['de' => 'Alt']])
                ],
                ['hash' => $invoiceHash]
            );
            $paidInvoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);
            $paidInvoice->changePaymentMethod($paymentMethod->getId(), 'Vollständig bezahlt', true);
            self::assertSame(Constants::PAYMENT_STATUS_PAID, (int)$paidInvoice->getAttribute('paid_status'));
            self::assertSame(
                $paymentMethod->getPaymentType()->getInvoiceInformationText($paidInvoice),
                $paidInvoice->getCustomDataEntry('InvoiceInformationText')
            );
            self::assertEqualsCanonicalizing(
                $transactionIds,
                array_map(
                    static fn($transaction) => $transaction->getTxId(),
                    TransactionHandler::getInstance()->getTransactionsByHash($invoiceHash)
                )
            );
        } finally {
            foreach ($transactionIds as $transactionId) {
                QUI::getDataBaseConnection()->delete(TransactionFactory::table(), ['txid' => $transactionId]);
            }

            QUI::getDataBaseConnection()->delete(Handler::getInstance()->invoiceTable(), ['hash' => $invoiceHash]);
            $paymentMethod->delete();
        }
    }

    public function testCanceledInvoiceCannotChangePaymentMethod(): void
    {
        $invoiceHash = QUI\Utils\Uuid::get();
        $this->insertInvoice($invoiceHash, Constants::PAYMENT_STATUS_CANCELED);

        try {
            $invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);
            $this->expectException(QUI\Exception::class);
            $invoice->changePaymentMethod(-1, 'Stornierte Rechnung');
        } finally {
            QUI::getDataBaseConnection()->delete(Handler::getInstance()->invoiceTable(), ['hash' => $invoiceHash]);
        }
    }

    public function testUnknownPaymentMethodDoesNotChangeInvoice(): void
    {
        $invoiceHash = QUI\Utils\Uuid::get();
        $this->insertInvoice($invoiceHash, Constants::PAYMENT_STATUS_OPEN);

        try {
            $invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);

            try {
                $invoice->changePaymentMethod(999999, 'Unbekannte Zahlungsart');
                self::fail('An unknown payment method must be rejected.');
            } catch (QUI\Exception) {
                $reloaded = Handler::getInstance()->getInvoiceByHash($invoiceHash);
                self::assertSame('-1', (string)$reloaded->getAttribute('payment_method'));
                self::assertSame('Previous payment text', $reloaded->getCustomDataEntry('InvoiceInformationText'));
            }
        } finally {
            QUI::getDataBaseConnection()->delete(Handler::getInstance()->invoiceTable(), ['hash' => $invoiceHash]);
        }
    }

    public function testPaymentMethodChangeRequiresNonEmptyReason(): void
    {
        $invoiceHash = QUI\Utils\Uuid::get();
        $this->insertInvoice($invoiceHash, Constants::PAYMENT_STATUS_OPEN);

        try {
            $invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);

            try {
                $invoice->changePaymentMethod(-1, " \n\t ");
                self::fail('A blank reason must be rejected.');
            } catch (QUI\Exception) {
                $reloaded = Handler::getInstance()->getInvoiceByHash($invoiceHash);
                self::assertSame('-1', (string)$reloaded->getAttribute('payment_method'));
                self::assertSame([], $reloaded->getHistory()->toArray());
            }
        } finally {
            QUI::getDataBaseConnection()->delete(Handler::getInstance()->invoiceTable(), ['hash' => $invoiceHash]);
        }
    }

    private function insertInvoice(string $hash, int $status): void
    {
        QUI::getDataBaseConnection()->insert(Handler::getInstance()->invoiceTable(), [
            'customer_id' => '',
            'hash' => $hash,
            'global_process_id' => $hash,
            'type' => Constants::TYPE_INVOICE,
            'ordered_by_name' => '',
            'payment_method' => -1,
            'payment_method_data' => json_encode(['id' => -1, 'title' => ['de' => 'Alt']]),
            'paid_status' => $status,
            'c_user' => QUI::getUsers()->getSystemUser()->getUUID(),
            'c_username' => 'system',
            'articles' => '{}',
            'isbrutto' => 0,
            'currency_data' => '{}',
            'nettosum' => 0,
            'nettosubsum' => 0,
            'subsum' => 0,
            'sum' => 10,
            'vat_array' => '[]',
            'transaction_invoice_text' => 'Existing transaction text',
            'custom_data' => json_encode(['InvoiceInformationText' => 'Previous payment text'])
        ]);
    }
}
