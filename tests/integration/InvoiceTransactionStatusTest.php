<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Methods\Invoice\Payment as InvoicePayment;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Order\EventHandling as OrderEvents;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\Interfaces\Users\User as UserInterface;
use Throwable;

class InvoiceTransactionStatusTest extends SqliteIntegrationTestCase
{
    private const TEST_PREFIX = 'invoice-transaction-status-';

    private ?UserInterface $previousSessionUser = null;
    private ?string $globalProcessId = null;
    private ?string $customerUuid = null;
    private ?string $orderHash = null;
    private ?int $paymentId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
        $this->globalProcessId = QUI\Utils\Uuid::get();
    }

    protected function tearDown(): void
    {
        $Connection = QUI::getDataBaseConnection();

        if ($this->globalProcessId !== null) {
            $Connection->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['global_process_id' => $this->globalProcessId]
            );
            $Connection->delete(
                Handler::getInstance()->invoiceTable(),
                ['global_process_id' => $this->globalProcessId]
            );
        }

        if ($this->orderHash !== null) {
            $Connection->delete(OrderHandler::getInstance()->table(), ['hash' => $this->orderHash]);
        }

        if ($this->paymentId !== null) {
            $Connection->delete(QUI::getDBTableName('payments'), ['id' => $this->paymentId]);
        }

        if ($this->customerUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->customerUuid);
            } catch (Throwable) {
            }
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }

        parent::tearDown();
    }

    /** @return array<string, array{bool, float, bool}> */
    public static function transactionCases(): array
    {
        return [
            'immediate order payment' => [false, 1.0, false],
            'pending order payment completed' => [true, 1.0, false],
            'pending partial order payment completed' => [true, 0.5, false],
            'pending direct invoice payment completed' => [true, 1.0, true]
        ];
    }

    #[DataProvider('transactionCases')]
    public function testPaymentAfterInvoiceCreation(bool $pending, float $paidFraction, bool $direct): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Transaction',
            'lastname' => 'Customer'
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Transaction',
            'lastname' => 'Customer',
            'street_no' => 'Teststraße 9',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        QUI::getDataBaseConnection()->insert(QUI::getDBTableName('payments'), [
            'active' => 1,
            'payment_type' => InvoicePayment::class,
            'icon' => '',
            'priority' => 1
        ]);
        $this->paymentId = (int)QUI::getDataBaseConnection()->lastInsertId();

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', $this->paymentId);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->addArticle($this->createArticle('TRANSACTION-STATUS'));
        $Invoice = $Draft->post($SystemUser);

        self::assertFalse($Invoice->isPaid());
        $this->orderHash = QUI\Utils\Uuid::get();
        $Connection = QUI::getDataBaseConnection();
        $Connection->insert(OrderHandler::getInstance()->table(), [
            'hash' => $this->orderHash,
            'global_process_id' => $this->globalProcessId,
            'customerId' => $User->getUUID(),
            'customer' => json_encode($User->getAttributes()),
            'addressInvoice' => $Address->toJSON(),
            'addressDelivery' => $Address->toJSON(),
            'articles' => json_encode($Draft->getArticles()->toArray()),
            'currency_data' => json_encode($Draft->getCurrency()->toArray()),
            'status' => 1,
            'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            'successful' => 1,
            'c_date' => '2026-10-01 10:00:00',
            'c_user' => $SystemUser->getUUID(),
            'payment_id' => $this->paymentId,
            'invoice_id' => $Invoice->getUUID()
        ]);
        $Connection->update(
            Handler::getInstance()->invoiceTable(),
            ['order_id' => $this->orderHash],
            ['hash' => $Invoice->getUUID()]
        );
        $Order = OrderHandler::getInstance()->getOrderByHash($this->orderHash);
        self::assertFalse($Order->isPaid());
        self::assertSame($Invoice->getUUID(), $Order->getInvoice()->getUUID());
        $originalTexts = $Connection->fetchAssociative(
            'SELECT custom_data, transaction_invoice_text FROM '
            . Handler::getInstance()->invoiceTable() . ' WHERE hash = ?',
            [$Invoice->getUUID()]
        );
        self::assertIsArray($originalTexts);
        $total = $Invoice->getPaidStatusInformation()['toPay'];
        $Transaction = TransactionFactory::createPaymentTransaction(
            round($total * $paidFraction, 2),
            $Invoice->getCurrency(),
            $direct ? $Invoice->getUUID() : $this->orderHash,
            '',
            [],
            $SystemUser,
            false,
            $this->globalProcessId,
            $pending ? TransactionHandler::STATUS_PENDING : TransactionHandler::STATUS_COMPLETE
        );

        try {
            // Ignore unrelated or stale entity links without skipping the actual invoice.
            $Transaction->addLinkedHash(QUI\Utils\Uuid::get());
            OrderEvents::onTransactionCreate($Transaction);
            EventHandler::onTransactionCreate($Transaction);
            self::assertTrue($Transaction->isHashLinked($Invoice->getUUID()));

            if ($pending) {
                $Transaction->complete();
                OrderEvents::onTransactionStatusChange($Transaction);
                EventHandler::onTransactionStatusChange($Transaction);
            }

            $storedInvoice = $Connection->fetchAssociative(
                'SELECT paid_status, paid_data, custom_data, transaction_invoice_text FROM '
                . Handler::getInstance()->invoiceTable() . ' WHERE hash = ?',
                [$Invoice->getUUID()]
            );
            self::assertIsArray($storedInvoice);
            $expectedStatus = $paidFraction === 1.0
                ? QUI\ERP\Constants::PAYMENT_STATUS_PAID
                : QUI\ERP\Constants::PAYMENT_STATUS_PART;
            self::assertSame($expectedStatus, (int)$storedInvoice['paid_status']);
            $paidData = json_decode($storedInvoice['paid_data'], true, 512, JSON_THROW_ON_ERROR);
            self::assertCount(1, $paidData);
            self::assertSame($Transaction->getTxId(), $paidData[0]['txid']);
            self::assertEqualsWithDelta(round($total * $paidFraction, 2), $paidData[0]['amount'], 0.001);
            self::assertSame($originalTexts['custom_data'], $storedInvoice['custom_data']);
            self::assertSame($originalTexts['transaction_invoice_text'], $storedInvoice['transaction_invoice_text']);

            if (!$direct) {
                $orderStatus = $Connection->fetchOne(
                    'SELECT paid_status FROM ' . OrderHandler::getInstance()->table() . ' WHERE hash = ?',
                    [$this->orderHash]
                );
                self::assertSame($expectedStatus, (int)$orderStatus);
            }
        } finally {
            $Connection->delete(TransactionFactory::table(), ['txid' => $Transaction->getTxId()]);
        }
    }

    private function createArticle(string $articleNumber): Article
    {
        return new Article([
            'id' => 1,
            'articleNo' => $articleNumber,
            'title' => 'Transaction status test article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]);
    }
}
