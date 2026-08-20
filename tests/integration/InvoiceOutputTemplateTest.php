<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Order\Handler as OrderHandler;
use QUI\Interfaces\Users\User as UserInterface;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use Throwable;

class InvoiceOutputTemplateTest extends SqliteIntegrationTestCase
{
    private const TEST_PREFIX = 'invoice-output-template-';

    private ?UserInterface $previousSessionUser = null;
    private ?string $globalProcessId = null;
    private ?string $customerUuid = null;
    private ?string $orderHash = null;

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

    public function testTemplateDataUsesNeutralCustomerWithoutAssignedCustomer(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setAttribute('payment_method', -1);
        $Draft->setCurrency('EUR');
        $Draft->addArticle($this->createArticle('NO-CUSTOMER'));
        $Draft->save($SystemUser);

        $templateData = OutputProviderInvoice::getTemplateData($Draft->getUUID());

        self::assertSame($Draft->getUUID(), $templateData['this']->getInvoice()->getUUID());
        self::assertInstanceOf(QUI\ERP\User::class, $templateData['Customer']);
        self::assertSame('', $templateData['Customer']->getAttribute('firstname'));
        self::assertInstanceOf(QUI\ERP\Address::class, $templateData['Address']);
        self::assertSame('', $templateData['Address']->getName());
        self::assertFalse($templateData['Address']->getAttribute('city'));
        self::assertFalse($templateData['DeliveryAddress']);
    }

    public function testTemplateDataSuppressesDuplicateDeliveryAddressAndResolvesOrder(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Output',
            'lastname' => 'Customer'
        ], $SystemUser);

        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Output',
            'lastname' => 'Customer',
            'street_no' => 'Template-Weg 7',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $this->orderHash = self::TEST_PREFIX . QUI\Utils\Uuid::get();
        $orderNumber = 'ORDER-OUTPUT-42';
        QUI::getDataBaseConnection()->insert(OrderHandler::getInstance()->table(), [
            'hash' => $this->orderHash,
            'id_str' => $orderNumber,
            'global_process_id' => $this->globalProcessId,
            'status' => 1,
            'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            'successful' => 1,
            'c_date' => '2026-08-20 12:00:00',
            'c_user' => $SystemUser->getUUID()
        ]);

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute('order_id', $this->orderHash);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->setDeliveryAddress(json_decode($Address->toJSON(), true, 512, JSON_THROW_ON_ERROR));
        $Draft->addArticle($this->createArticle('ORDER-LINK'));

        $Invoice = $Draft->post($SystemUser);
        $templateData = OutputProviderInvoice::getTemplateData($Invoice->getUUID());

        self::assertSame($User->getUUID(), $templateData['Customer']->getUUID());
        self::assertFalse($templateData['DeliveryAddress']);
        self::assertSame($orderNumber, $templateData['orderNumber']);

        $differentDeliveryAddress = json_decode($Address->toJSON(), true, 512, JSON_THROW_ON_ERROR);
        $differentDeliveryAddress['city'] = 'Hamburg';
        QUI::getDataBaseConnection()->update(
            Handler::getInstance()->invoiceTable(),
            ['delivery_address' => json_encode($differentDeliveryAddress, JSON_THROW_ON_ERROR)],
            ['hash' => $Invoice->getUUID()]
        );

        $differentTemplateData = OutputProviderInvoice::getTemplateData($Invoice->getUUID());

        self::assertInstanceOf(QUI\ERP\Address::class, $differentTemplateData['DeliveryAddress']);
        self::assertSame('Hamburg', $differentTemplateData['DeliveryAddress']->getAttribute('city'));
    }

    private function createArticle(string $articleNumber): Article
    {
        return new Article([
            'id' => 1,
            'articleNo' => $articleNumber,
            'title' => 'Output template test article',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]);
    }
}
