<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use horstoeko\zugferd\ZugferdDocumentReader;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\Interfaces\Users\User as UserInterface;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use Throwable;

class ElectronicInvoiceFallbackTest extends SqliteIntegrationTestCase
{
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
        if ($this->globalProcessId !== null) {
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['global_process_id' => $this->globalProcessId]
            );
            QUI::getDataBaseConnection()->delete(
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

    public function testElectronicInvoiceUsesConfiguredBuyerEmailFallback(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = 'invoice-electronic-fallback-' . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => '',
            'firstname' => 'Electronic',
            'lastname' => 'Fallback',
            'customerId' => (string)random_int(920000, 929999)
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Electronic',
            'lastname' => 'Fallback',
            'street_no' => 'Fallback-Weg 10',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => ''
        ], $SystemUser);

        self::assertEmpty($Address->getAttribute('email'));
        self::assertEmpty($User->getAttribute('email'));

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'E-INVOICE-FALLBACK',
            'title' => 'Electronic invoice fallback test',
            'unitPrice' => 10,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Invoice = $Draft->post($SystemUser);

        $InvoiceConfig = QUI::getPackage('quiqqer/invoice')->getConfig();
        $ErpConfig = QUI::getPackage('quiqqer/erp')->getConfig();
        self::assertNotNull($InvoiceConfig);
        self::assertNotNull($ErpConfig);
        $originalFallback = $InvoiceConfig->getValue('invoice', 'electronicInvoiceBuyerEmailFallback');
        $originalCompanyName = $ErpConfig->getValue('company', 'name');

        try {
            $InvoiceConfig->setValue(
                'invoice',
                'electronicInvoiceBuyerEmailFallback',
                'billing+%HASH%@example.invalid'
            );
            $InvoiceConfig->save();
            $ErpConfig->setValue('company', 'name', 'PHPUnit Seller');
            $ErpConfig->save();

            $Document = QUI\ERP\Accounting\Invoice\Utils\Invoice::getElectronicInvoice($Invoice);
            $Reader = ZugferdDocumentReader::readAndGuessFromContent($Document->getContent());
            $scheme = null;
            $buyerEmail = null;
            $Reader->getDocumentBuyerCommunication($scheme, $buyerEmail);

            self::assertSame('EM', $scheme);
            self::assertSame('billing+' . $Invoice->getUUID() . '@example.invalid', $buyerEmail);
        } finally {
            $InvoiceConfig->setValue('invoice', 'electronicInvoiceBuyerEmailFallback', $originalFallback);
            $InvoiceConfig->save();
            $ErpConfig->setValue('company', 'name', $originalCompanyName);
            $ErpConfig->save();
        }
    }
}
