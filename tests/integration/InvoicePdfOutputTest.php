<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use DateTime;
use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdProfileResolver;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\HtmlToPdf\Document;
use QUI\HtmlToPdf\PdfCreator;
use QUI\HtmlToPdf\Provider\Pdf\Mpdf\Provider as MpdfProvider;
use QUI\Interfaces\Users\User as UserInterface;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use ReflectionProperty;
use Throwable;

class InvoicePdfOutputTest extends SqliteIntegrationTestCase
{
    private const TEST_PREFIX = 'invoice-pdf-output-';

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
            $this->connection->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['global_process_id' => $this->globalProcessId]
            );
            $this->connection->delete(
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

    public function testInvoiceViewCreatesCompleteZugferdPdf(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'PDF',
            'lastname' => 'Customer'
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'PDF',
            'lastname' => 'Customer',
            'street_no' => 'Document Street 42',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency('EUR');
        $Draft->addArticle(new Article([
            'id' => 1,
            'articleNo' => 'PDF-E2E-42',
            'title' => 'PDF end-to-end article',
            'unitPrice' => 42,
            'quantity' => 1,
            'vat' => 19
        ]));
        $Invoice = $Draft->post($SystemUser);

        $InvoiceConfig = QUI::getPackage('quiqqer/invoice')->getConfig();
        $ErpConfig = QUI::getPackage('quiqqer/erp')->getConfig();
        self::assertNotNull($InvoiceConfig);
        self::assertNotNull($ErpConfig);
        $previousZugferdAttachment = $InvoiceConfig->getValue('invoice', 'zugferdInvoiceAttachment');
        $previousZugferdAttachmentType = $InvoiceConfig->getValue('invoice', 'zugferdInvoiceAttachmentType');
        $previousCompanyName = $ErpConfig->getValue('company', 'name');
        $pdfFile = null;
        $pdfEventCallback = [EventHandler::class, 'onQuiqqerHtmlToPDFCreated'];
        $pdfEventAdded = false;

        try {
            $InvoiceConfig->setValue('invoice', 'zugferdInvoiceAttachment', 1);
            $InvoiceConfig->setValue('invoice', 'zugferdInvoiceAttachmentType', 2);
            $InvoiceConfig->save();
            $ErpConfig->setValue('company', 'name', 'PHPUnit Seller');
            $ErpConfig->save();

            if (!$this->hasPdfCreatedEventCallback()) {
                QUI::getEvents()->addEvent('onQuiqqerHtmlToPDFCreated', $pdfEventCallback);
                $pdfEventAdded = true;
            }

            $Document = $Invoice->getView()->toPDF();

            self::assertInstanceOf(Document::class, $Document);
            self::assertSame($Invoice->getUUID(), $Document->getAttribute('Entity')->getUUID());
            self::assertStringEndsWith('.pdf', $Document->options->filename);
            self::assertNotSame('', trim($Document->getHeaderHTML()));
            self::assertNotSame('', trim($Document->getFooterHTML()));
            self::assertStringContainsString('PDF end-to-end article', $Document->getContentHTML());
            self::assertStringContainsString($Invoice->getPrefixedNumber(), $Document->getHeaderHTML());

            $PdfCreator = new PdfCreator((new MpdfProvider())->getHtmlToPdfCreator());
            $pdfFile = $PdfCreator->createPdf($Document);
            $pdfContent = file_get_contents($pdfFile);

            self::assertFileExists($pdfFile);
            self::assertIsString($pdfContent);
            self::assertStringStartsWith('%PDF-', $pdfContent);
            self::assertGreaterThan(1000, strlen($pdfContent));

            $zugferdXml = ZugferdDocumentPdfReader::getXmlFromFile($pdfFile);
            self::assertSame(2, ZugferdProfileResolver::resolveProfileId($zugferdXml));

            $Reader = ZugferdDocumentPdfReader::readAndGuessFromFile($pdfFile);
            $documentNo = null;
            $documentTypeCode = null;
            $documentDate = null;
            $invoiceCurrency = null;
            $taxCurrency = null;
            $documentName = null;
            $documentLanguage = null;
            $effectiveSpecifiedPeriod = null;
            $Reader->getDocumentInformation(
                $documentNo,
                $documentTypeCode,
                $documentDate,
                $invoiceCurrency,
                $taxCurrency,
                $documentName,
                $documentLanguage,
                $effectiveSpecifiedPeriod
            );

            self::assertSame($Invoice->getPrefixedNumber(), $documentNo);
            self::assertSame('EUR', $invoiceCurrency);
            self::assertInstanceOf(DateTime::class, $documentDate);
        } finally {
            if ($pdfEventAdded) {
                QUI::getEvents()->removeEvent('onQuiqqerHtmlToPDFCreated', $pdfEventCallback);
            }

            $this->restoreConfigValue(
                $InvoiceConfig,
                'invoice',
                'zugferdInvoiceAttachment',
                $previousZugferdAttachment
            );
            $this->restoreConfigValue(
                $InvoiceConfig,
                'invoice',
                'zugferdInvoiceAttachmentType',
                $previousZugferdAttachmentType
            );
            $InvoiceConfig->save();
            $this->restoreConfigValue($ErpConfig, 'company', 'name', $previousCompanyName);
            $ErpConfig->save();

            if (is_string($pdfFile) && file_exists($pdfFile)) {
                unlink($pdfFile);
            }
        }
    }

    private function hasPdfCreatedEventCallback(): bool
    {
        $EventsProperty = new ReflectionProperty(QUI::getEvents(), 'Events');
        $events = $EventsProperty->getValue(QUI::getEvents())->getList();
        $callback = EventHandler::class . '::onQuiqqerHtmlToPDFCreated';

        foreach ($events['onQuiqqerHtmlToPDFCreated'] ?? [] as $event) {
            $registeredCallback = $event['callable'];

            if (is_string($registeredCallback) && ltrim($registeredCallback, '\\') === $callback) {
                return true;
            }

            if (
                is_array($registeredCallback)
                && $registeredCallback === [
                    EventHandler::class,
                    'onQuiqqerHtmlToPDFCreated'
                ]
            ) {
                return true;
            }
        }

        return false;
    }

    private function restoreConfigValue(QUI\Config $Config, string $section, string $key, mixed $value): void
    {
        if ($value === false) {
            $Config->del($section, $key);
            return;
        }

        $Config->setValue($section, $key, $value);
    }
}
