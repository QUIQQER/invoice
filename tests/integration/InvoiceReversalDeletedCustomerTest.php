<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\Interfaces\Users\User as UserInterface;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use Throwable;

class InvoiceReversalDeletedCustomerTest extends SqliteIntegrationTestCase
{
    private const TEST_PREFIX = 'invoice-reversal-deleted-customer-';

    private ?UserInterface $previousSessionUser = null;
    private ?string $globalProcessId = null;
    private ?string $customerUuid = null;

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

    public function testInvoiceCanBeReversedAfterCustomerWasDeleted(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Deleted',
            'lastname' => 'Customer'
        ], $SystemUser);

        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Deleted',
            'lastname' => 'Customer',
            'street_no' => 'Teststraße 1',
            'zip' => '12345',
            'city' => 'Teststadt',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'TEST-1',
            'title' => 'Regression test article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));

        $Invoice = $Draft->post($SystemUser);
        $originalCustomerId = $Invoice->getAttribute('customer_id');

        self::assertTrue($Users->deleteUser($this->customerUuid));

        $Reversal = $Invoice->reversal('Regression test', $SystemUser);

        self::assertInstanceOf(Invoice::class, $Reversal);
        self::assertSame($originalCustomerId, $Reversal->getAttribute('customer_id'));
        self::assertSame('Deleted', $Reversal->getCustomer()->getAttribute('firstname'));
        self::assertSame('Customer', $Reversal->getCustomer()->getAttribute('lastname'));
    }

    public function testStornoAliasCreatesAndPersistsReversal(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Storno',
            'lastname' => 'Customer'
        ], $SystemUser);

        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Storno',
            'lastname' => 'Customer',
            'street_no' => 'Teststraße 2',
            'zip' => '54321',
            'city' => 'Teststadt',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'STORNO-1',
            'title' => 'Storno alias test article',
            'unitPrice' => 20,
            'quantity' => 1,
            'vat' => 19
        ]));

        $Invoice = $Draft->post($SystemUser);
        $reason = 'Direct storno alias test';
        $reversalUuid = $Invoice->storno($reason, $SystemUser);
        $Reversal = Handler::getInstance()->getInvoiceByHash($reversalUuid);
        $canceledInvoiceData = Handler::getInstance()->getInvoiceData($Invoice->getUUID());

        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_REVERSAL, $Reversal->getInvoiceType());
        self::assertSame($Invoice->getGlobalProcessId(), $Reversal->getGlobalProcessId());
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_CANCEL, (int)$canceledInvoiceData['type']);
        self::assertSame(
            QUI\ERP\Constants::PAYMENT_STATUS_CANCELED,
            (int)$canceledInvoiceData['paid_status']
        );

        $storedData = json_decode((string)$canceledInvoiceData['data'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($reversalUuid, $storedData['canceledId']);
        self::assertContains($reason, array_column($Invoice->getComments()->toArray(), 'message'));
    }
}
