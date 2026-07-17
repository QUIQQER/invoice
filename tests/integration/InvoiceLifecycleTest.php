<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Article;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\McpProvider;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCancelled;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCreditNote;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Accounting\Invoice\PaymentReceiver;
use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;
use QUI\ERP\Accounting\Payments\Transactions\Factory as TransactionFactory;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\Mail\Mailer;
use QUI\Smarty\Collector;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

class InvoiceLifecycleTest extends TestCase
{
    private const TEST_PREFIX = 'invoice-lifecycle-';

    private ?UserInterface $previousSessionUser = null;
    private ?string $globalProcessId = null;
    private ?string $customerUuid = null;

    /** @var list<string> */
    private array $transactionIds = [];

    protected function setUp(): void
    {
        try {
            QUI::getDataBaseConnection()->executeQuery('SELECT 1')->free();
        } catch (Throwable $Exception) {
            self::markTestSkipped('QUIQQER database is not available: ' . $Exception->getMessage());
        }

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

        foreach ($this->transactionIds as $transactionId) {
            $Connection->delete(TransactionFactory::table(), ['txid' => $transactionId]);
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }
    }

    public function testDraftCanBeEditedReloadedAndPosted(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $username = self::TEST_PREFIX . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Lifecycle',
            'lastname' => 'Customer'
        ], $SystemUser);

        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'Lifecycle',
            'lastname' => 'Customer',
            'street_no' => 'Teststraße 2',
            'zip' => '54321',
            'city' => 'Teststadt',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $Handler = Handler::getInstance();
        $Draft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);

        self::assertSame($Draft->getId(), $Draft->getCleanId());
        self::assertSame($Draft->getUUID(), $Draft->getHash());
        self::assertSame($this->globalProcessId, $Draft->getGlobalProcessId());
        self::assertStringContainsString((string)$Draft->getId(), $Draft->getPrefixedNumber());
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY, $Draft->getInvoiceType());
        self::assertNull($Draft->getCustomer());
        self::assertNull($Draft->getOrderedByUser());
        self::assertNull($Draft->getShipping());
        self::assertFalse($Draft->hasRefund());
        self::assertNull($Draft->reversal());
        self::assertFalse($Draft->getData('missing'));
        self::assertFalse($Draft->getPaymentData('missing'));
        self::assertNull($Draft->getCustomDataEntry('missing'));
        self::assertGreaterThan(0, (new QUI\ERP\Accounting\Invoice\NumberRanges\Invoice())->getRange());
        self::assertGreaterThan(0, (new QUI\ERP\Accounting\Invoice\NumberRanges\TemporaryInvoice())->getRange());

        $Settings = QUI\ERP\Accounting\Invoice\Settings::getInstance();
        self::assertIsArray($Settings->getAvailableTemplates());
        self::assertIsString($Settings->getDefaultTemplate());
        self::assertIsBool($Settings->isIncludeQrCode());

        $ProcessingStatuses = QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler::getInstance();
        $processingStatusList = $ProcessingStatuses->getList();
        self::assertIsArray($processingStatusList);
        self::assertIsArray($ProcessingStatuses->getProcessingStatusList());

        $Draft->setCustomer($User);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $Draft->setCurrency(QUI\ERP\Defaults::getCurrency()->getCode());
        $Draft->setShipping(new QUI\ERP\Shipping\Types\ShippingEntry());
        self::assertInstanceOf(QUI\ERP\Shipping\Types\ShippingEntry::class, $Draft->getShipping());
        $Draft->setDeliveryAddress([
            'firstname' => 'Delivery',
            'lastname' => 'Customer',
            'street_no' => 'Lieferweg 3',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'ignored' => 'value'
        ]);
        $Draft->removeDeliveryAddress();
        self::assertSame('Teststadt', $Draft->getDeliveryAddress()?->getAttribute('city'));
        $Draft->setDeliveryAddress([
            'firstname' => 'Delivery',
            'lastname' => 'Customer',
            'street_no' => 'Lieferweg 3',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ]);
        $Draft->setData('integration', ['draft' => true]);
        $Draft->setPaymentData('reference', 'PAY-123');
        $Draft->addCustomDataEntry('source', 'phpunit');
        $Draft->addHistory('Lifecycle history');
        $Draft->addComment('<b>Lifecycle comment</b><script>removed</script>');

        $Draft->importArticles([
            'articles' => [[
                'id' => 1,
                'articleNo' => 'TEST-IMPORT',
                'title' => 'Imported test article',
                'unitPrice' => 4,
                'quantity' => 2,
                'vat' => 19
            ]]
        ]);
        $Draft->addArticle($this->createArticle('TEST-REMOVE', 1));
        self::assertSame(2, $Draft->getArticles()->count());
        $Draft->removeArticle(1);
        self::assertSame(1, $Draft->getArticles()->count());
        $Draft->clearArticles();
        self::assertSame(0, $Draft->getArticles()->count());
        $Draft->addArticle($this->createArticle('TEST-FINAL', 10));
        $Draft->getArticles()->calc();

        $Draft->setInvoiceType(999, $SystemUser);
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY, $Draft->getInvoiceType());
        $Draft->setInvoiceType(QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY, $SystemUser);

        self::assertSame('DE', $Draft->getDeliveryAddress()?->getAttribute('country'));
        self::assertSame('EUR', $Draft->getCurrency()->getCode());
        self::assertSame('Lifecycle', $Draft->getCustomer()?->getAttribute('firstname'));
        self::assertSame(['draft' => true], $Draft->getData('integration'));
        self::assertSame('PAY-123', $Draft->getPaymentData('reference'));
        self::assertSame('phpunit', $Draft->getCustomDataEntry('source'));
        self::assertCount(1, $Draft->getCustomData());
        self::assertFalse($Draft->getComments()->isEmpty());
        self::assertFalse($Draft->getHistory()->isEmpty());
        self::assertSame(1, $Draft->getArticles()->count());
        self::assertSame(11.9, $Draft->getPriceCalculation()->getSum()->value());
        $DraftView = $Draft->getView();
        self::assertSame($Draft, $DraftView->getInvoice());
        self::assertTrue($DraftView->isDraft());
        self::assertIsString($DraftView->getDate());
        self::assertIsString($DraftView->formatDate('2026-07-17'));
        self::assertNotSame('', $DraftView->getDownloadLink());
        self::assertNotSame('', $DraftView->getTransactionText());
        self::assertStringContainsString('<style>', $DraftView->previewOnlyArticles());
        self::assertIsNotString($Draft->getArticles()->getCalculations()['subSum']);
        self::assertArrayHasKey('entityType', $Draft->toArray());
        self::assertFalse($Draft->isPaid());
        self::assertArrayHasKey('toPay', $Draft->getPaidStatusInformation());

        $Draft->lock();

        try {
            self::assertFalse($Draft->isLocked());
        } finally {
            $this->replaceSessionUser($SystemUser);
            $Draft->unlock();
        }

        self::assertFalse($Draft->isLocked());
        $Draft->checkLocked();

        $Draft->save($SystemUser);
        $Draft->validate();

        $ReloadedDraft = $Handler->getTemporaryInvoiceByHash($Draft->getUUID());
        self::assertSame($Draft->getId(), $ReloadedDraft->getId());
        self::assertSame('PAY-123', $ReloadedDraft->getPaymentData('reference'));
        self::assertSame(['draft' => true], $ReloadedDraft->getData('integration'));
        self::assertSame('phpunit', $ReloadedDraft->getCustomDataEntry('source'));
        self::assertSame(1, $ReloadedDraft->getArticles()->count());
        self::assertSame($ReloadedDraft->getUUID(), $Handler->get($ReloadedDraft->getUUID())->getUUID());
        self::assertSame(1, $Handler->countTemporaryInvoices([
            'where' => ['global_process_id' => $this->globalProcessId]
        ]));
        self::assertCount(1, $Handler->searchTemporaryInvoices([
            'where' => ['global_process_id' => $this->globalProcessId]
        ]));

        $McpProvider = new McpProvider();
        $getTemporaryInvoice = new ReflectionMethod($McpProvider, 'getTemporaryInvoice');
        self::assertSame(
            $ReloadedDraft->getUUID(),
            $getTemporaryInvoice->invoke($McpProvider, $ReloadedDraft->getUUID())->getUUID()
        );

        $Invoice = $ReloadedDraft->post($SystemUser);

        self::assertInstanceOf(Invoice::class, $Invoice);
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE, $Invoice->getInvoiceType());
        self::assertSame($this->globalProcessId, $Invoice->getGlobalProcessId());
        self::assertSame($Invoice->getUUID(), $Invoice->getHash());
        self::assertSame($Invoice->getId(), $Invoice->getCleanId());
        self::assertSame($Invoice->getPrefixedNumber(), $Invoice->getPrefixedId());
        self::assertSame(1, $Invoice->getArticles()->count());
        self::assertSame('EUR', $Invoice->getCurrency()->getCode());
        self::assertSame('Lifecycle', $Invoice->getCustomer()->getAttribute('firstname'));
        self::assertSame('PAY-123', $Invoice->getPaymentData('reference'));
        self::assertSame('phpunit', $Invoice->getCustomDataEntry('source'));
        self::assertArrayHasKey('source', $Invoice->getCustomData());
        self::assertSame(['draft' => true], $Invoice->getData('integration'));
        self::assertSame('DE', $Invoice->getInvoiceAddress()?->getAttribute('country'));
        self::assertSame('Berlin', $Invoice->getDeliveryAddress()?->getAttribute('city'));
        self::assertSame($Invoice, $Invoice->getView()->getInvoice());
        self::assertFalse($Invoice->getView()->isDraft());
        self::assertNotSame('', $Invoice->getView()->getTransactionText());
        self::assertNotSame('', $Invoice->getView()->getDownloadLink());
        self::assertIsString($Invoice->getView()->toHTML());
        self::assertIsString($Invoice->getView()->previewHTML());
        self::assertSame(11.9, $Invoice->getPriceCalculation()->getSum()->value());
        self::assertArrayHasKey('prefixedNumber', $Invoice->toArray());
        self::assertFalse($Invoice->getComments()->isEmpty());
        self::assertFalse($Invoice->getHistory()->isEmpty());
        self::assertFalse($Invoice->hasRefund());
        self::assertFalse($Invoice->isPaid());
        self::assertArrayHasKey('toPay', $Invoice->getPaidStatusInformation());
        self::assertSame('PAY-123', $Invoice->getPaymentDataEntry('reference'));
        self::assertIsString($Invoice->getPayment()->getTitle());
        self::assertNull($Invoice->getShipping());
        $Invoice->calculatePayments();
        $Invoice->setPaymentStatus(QUI\ERP\Constants::PAYMENT_STATUS_OPEN);
        $Invoice->setProcessingStatus(-1);
        self::assertNull($Invoice->getProcessingStatus());

        if ($processingStatusList !== []) {
            $processingStatusId = (int)array_key_first($processingStatusList);
            $Invoice->setProcessingStatus($processingStatusId);
            self::assertSame($processingStatusId, $Invoice->getProcessingStatus()?->getId());
        }

        $Invoice->addComment('', $SystemUser);
        $Invoice->setCustomer(['id' => 'ignored']);

        $parseInvoice = new ReflectionMethod($McpProvider, 'parseInvoice');
        $mcpInvoice = $parseInvoice->invoke($McpProvider, $Invoice, true);
        self::assertSame($Invoice->getUUID(), $mcpInvoice['uuid']);
        self::assertCount(1, $mcpInvoice['articles']['articles']);
        self::assertArrayNotHasKey('articles', $parseInvoice->invoke($McpProvider, $Invoice, false));

        $getInvoice = new ReflectionMethod($McpProvider, 'getInvoice');
        self::assertSame($Invoice->getUUID(), $getInvoice->invoke($McpProvider, $Invoice->getUUID())->getUUID());

        $placeholders = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoicePlaceholders($Invoice);
        self::assertSame($Invoice->getUUID(), $placeholders['%HASH%']);
        self::assertSame($Invoice->getPrefixedNumber(), $placeholders['%INO%']);
        self::assertNotSame('', QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceFilename($Invoice));
        self::assertSame([], QUI\ERP\Accounting\Invoice\Utils\Invoice::getTransactionsByInvoice($Invoice));
        self::assertIsBool(QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirement());
        self::assertIsFloat(QUI\ERP\Accounting\Invoice\Utils\Invoice::addressRequirementThreshold());
        QUI\ERP\Accounting\Invoice\Utils\Invoice::checkAddress($Address);

        $ElectronicInvoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getElectronicInvoice($Invoice);
        self::assertInstanceOf(\horstoeko\zugferd\ZugferdDocumentBuilder::class, $ElectronicInvoice);

        $Invoice->addCustomDataEntry('posted', true);
        $Invoice->addHistory('Posted lifecycle history');
        $Invoice->addComment('<i>Posted lifecycle comment</i>', $SystemUser);
        self::assertTrue($Invoice->getCustomDataEntry('posted'));

        self::assertSame($Invoice->getUUID(), $Handler->getInvoiceByHash($Invoice->getUUID())->getUUID());
        self::assertSame($Invoice->getId(), $Handler->getInvoice($Invoice->getId())->getId());
        self::assertSame($Invoice->getId(), $Handler->getInvoiceData($Invoice->getUUID())['id']);
        self::assertSame(1, $Handler->count([
            'where' => ['global_process_id' => $this->globalProcessId]
        ]));
        self::assertCount(1, $Handler->search([
            'where' => ['global_process_id' => $this->globalProcessId]
        ]));
        self::assertCount(1, $Handler->getInvoicesByGlobalProcessId($this->globalProcessId));

        $Search = new InvoiceSearch();
        $Search->setFilter('search', $Invoice->getUUID());
        $Search->setFilter('currency', $Invoice->getCurrency()->getCode());
        $Search->order('display_sum DESC');
        $Search->limit(0, 20);
        self::assertSame($Invoice->getId(), (int)$Search->search()[0]['id']);

        $GridResult = $Search->searchForGrid();
        self::assertArrayHasKey('total', $GridResult);
        self::assertArrayHasKey('grid', $GridResult);
        self::assertSame(1, $GridResult['grid']['total']);

        $Search->disableCalcTotal();
        self::assertArrayHasKey('total', $Search->searchForGrid());
        $Search->enableCalcTotal();
        $Search->noLimit();
        self::assertSame($Invoice->getId(), (int)$Search->search()[0]['id']);
        $Search->clearFilter();

        self::assertSame('Invoice', OutputProviderInvoice::getEntityType());
        self::assertSame('CreditNote', OutputProviderCreditNote::getEntityType());
        self::assertSame('Canceled', OutputProviderCancelled::getEntityType());
        self::assertNotSame('', OutputProviderInvoice::getEntityTypeTitle());
        self::assertNotSame('', OutputProviderCreditNote::getEntityTypeTitle());
        self::assertNotSame('', OutputProviderCancelled::getEntityTypeTitle());
        self::assertSame($Invoice->getUUID(), OutputProviderInvoice::getEntity($Invoice->getUUID())->getUUID());
        self::assertNotSame('', OutputProviderInvoice::getDownloadFileName($Invoice->getUUID()));
        self::assertSame(
            $Invoice->getCustomer()->getLocale()->getCurrent(),
            OutputProviderInvoice::getLocale($Invoice->getUUID())->getCurrent()
        );
        self::assertSame($username . '@example.invalid', OutputProviderInvoice::getEmailAddress($Invoice->getUUID()));
        self::assertNotSame('', OutputProviderInvoice::getMailSubject($Invoice->getUUID()));
        self::assertNotSame('', OutputProviderInvoice::getMailBody($Invoice->getUUID()));
        self::assertNotSame('', OutputProviderInvoice::dateFormat('2026-07-17', $Invoice->getCustomer()->getLocale()));
        self::assertNotSame('', OutputProviderInvoice::dateFormat(null, $Invoice->getCustomer()->getLocale()));
        self::assertArrayHasKey('companyOrName', OutputProviderInvoice::getCustomerVariables($Invoice->getCustomer()));

        $QrCodeMethod = new ReflectionMethod(OutputProviderInvoice::class, 'getEpcQrCodeImageImgSrc');
        self::assertFalse($QrCodeMethod->invoke(null, $Invoice));

        $templateData = OutputProviderInvoice::getTemplateData($Invoice->getUUID());
        self::assertSame($Invoice->getUUID(), $templateData['this']->getInvoice()->getUUID());
        self::assertSame($Invoice->getCustomer()->getUUID(), $templateData['Customer']->getUUID());
        self::assertFalse(OutputProviderInvoice::hasDownloadPermission($Invoice->getUUID(), $SystemUser));
        $this->replaceSessionUser($User);
        self::assertTrue(OutputProviderInvoice::hasDownloadPermission($Invoice->getUUID(), $User));
        $this->replaceSessionUser($SystemUser);

        $Receiver = new PaymentReceiver($Invoice->getUUID());
        self::assertSame('Invoice', PaymentReceiver::getType());
        self::assertNotSame('', PaymentReceiver::getTypeTitle());
        self::assertSame($Invoice->getPrefixedNumber(), $Receiver->getDocumentNo());
        self::assertSame($Invoice->getCustomer()->getCustomerNo(), $Receiver->getDebtorNo());
        self::assertSame($Invoice->getCurrency()->getCode(), $Receiver->getCurrency()->getCode());
        self::assertSame((float)$Invoice->getAttribute('sum'), $Receiver->getAmountTotal());
        self::assertSame((float)$Invoice->getAttribute('toPay'), $Receiver->getAmountOpen());
        self::assertSame((float)$Invoice->getAttribute('paid'), $Receiver->getAmountPaid());
        self::assertSame((int)$Invoice->getAttribute('paid_status'), $Receiver->getPaymentStatus());
        self::assertInstanceOf(\DateTime::class, $Receiver->getDate());
        self::assertNotFalse($Receiver->getDueDate());
        self::assertNotFalse($Receiver->getDebtorAddress());
        self::assertNotFalse($Receiver->getPaymentMethod());

        $Comments = new QUI\ERP\Comments();
        EventHandler::onQuiqqerErpGetCommentsByUser($User, $Comments);
        self::assertFalse($Comments->isEmpty());

        $History = new QUI\ERP\Comments();
        EventHandler::onQuiqqerErpGetHistoryByUser($User, $History);
        self::assertFalse($History->isEmpty());

        $Mailer = new Mailer();
        EventHandler::onQuiqqerErpOutputSendMailBefore(
            $Invoice->getUUID(),
            'Unsupported',
            $username . '@example.invalid',
            $Mailer
        );
        EventHandler::onQuiqqerErpOutputSendMailBefore(
            $Invoice->getUUID(),
            OutputProviderInvoice::getEntityType(),
            $username . '@example.invalid',
            $Mailer
        );
        EventHandler::onQuiqqerErpOutputSendMail(
            $Invoice->getUUID(),
            OutputProviderInvoice::getEntityType(),
            $username . '@example.invalid'
        );
        EventHandler::onQuiqqerErpOutputSendMail($Invoice->getUUID(), 'Unsupported', 'nobody@example.invalid');
        EventHandler::onQuiqqerErpOutputSendMail(QUI\Utils\Uuid::get(), OutputProviderInvoice::getEntityType(), '');
        EventHandler::onQuiqqerErpOutputSendMailBefore(
            QUI\Utils\Uuid::get(),
            OutputProviderInvoice::getEntityType(),
            '',
            $Mailer
        );

        $Document = new QUI\HtmlToPdf\Document();
        EventHandler::onQuiqqerHtmlToPDFCreated($Document, '/not/a/real/document.pdf');
        $Document->setAttribute('Entity', $Invoice);
        EventHandler::onQuiqqerHtmlToPDFCreated($Document, '/not/a/real/document.pdf');

        $Collector = new Collector();
        EventHandler::onFrontendUsersAddressTop($Collector, $User);
        self::assertStringContainsString('<style>', $Collector->getContent());
        EventHandler::onUserSaveBegin($User);
        EventHandler::onPackageSetup(QUI::getPackage('quiqqer/core'));
        EventHandler::onProductsPackageSetup(QUI::getPackage('quiqqer/core'));

        $CreditNoteDraft = $Invoice->createCreditNote($SystemUser);
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE, $CreditNoteDraft->getInvoiceType());
        self::assertSame($this->globalProcessId, $CreditNoteDraft->getGlobalProcessId());
        self::assertLessThan(0, $CreditNoteDraft->getArticles()->getArticles()[0]->getUnitPrice()->value());

        $CreditNote = $CreditNoteDraft->post($SystemUser);
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE, $CreditNote->getInvoiceType());
        self::assertNotSame('', $CreditNote->getView()->getDownloadLink());
        self::assertNotSame('', OutputProviderCreditNote::getMailSubject($CreditNote->getUUID()));
        self::assertNotSame('', OutputProviderCreditNote::getMailBody($CreditNote->getUUID()));
        self::assertInstanceOf(
            \horstoeko\zugferd\ZugferdDocumentBuilder::class,
            QUI\ERP\Accounting\Invoice\Utils\Invoice::getElectronicInvoice($CreditNote)
        );

        try {
            $CreditNote->createCreditNote($SystemUser);
            self::fail('A credit note must not create another credit note.');
        } catch (QUI\ERP\Exception) {
            self::assertTrue(true);
        }

        $CopiedDraft = $Invoice->copy($SystemUser, $this->globalProcessId);
        $CopiedDraft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);

        $applyTemporaryInvoiceData = new ReflectionMethod($McpProvider, 'applyTemporaryInvoiceData');
        $applyTemporaryInvoiceData->invoke($McpProvider, $CopiedDraft, [
            'customer_id' => $User->getUUID(),
            'invoice_address_id' => $Address->getUUID(),
            'invoice_address' => $Address->toJSON(),
            'addressDelivery' => json_encode([
                'firstname' => 'MCP',
                'lastname' => 'Delivery',
                'street_no' => 'MCP-Weg 4',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE'
            ]),
            'articles' => [[
                'id' => 2,
                'articleNo' => 'MCP-ARTICLE',
                'title' => 'MCP updated article',
                'unitPrice' => 5,
                'quantity' => 1,
                'vat' => 19
            ]],
            'currency' => 'EUR',
            'currencyRate' => 1,
            'service_period' => '2026-07-01 - 2026-07-31',
            'project_name' => 'MCP update'
        ]);

        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY, $CopiedDraft->getInvoiceType());
        self::assertSame($this->globalProcessId, $CopiedDraft->getGlobalProcessId());
        self::assertSame('MCP update', $CopiedDraft->getAttribute('project_name'));
        self::assertSame(1, $CopiedDraft->getArticles()->count());

        $CopiedInvoice = $CopiedDraft->post($SystemUser);
        $CancellationUuid = $CopiedInvoice->cancellation('Lifecycle cancellation', $SystemUser);
        $Cancellation = $Handler->getInvoiceByHash($CancellationUuid);
        self::assertSame(QUI\ERP\Constants::TYPE_INVOICE_REVERSAL, $Cancellation->getInvoiceType());
        self::assertNotSame('', $Cancellation->getView()->getDownloadLink());
        self::assertNotSame('', OutputProviderCancelled::getMailSubject($Cancellation->getUUID()));
        self::assertNotSame('', OutputProviderCancelled::getMailBody($Cancellation->getUUID()));

        $Transaction = TransactionFactory::createPaymentTransaction(
            5,
            $Invoice->getCurrency(),
            $Invoice->getUUID(),
            'phpunit',
            [],
            $SystemUser,
            '2026-07-17 12:00:00',
            $this->globalProcessId
        );
        $this->transactionIds[] = $Transaction->getTxId();
        EventHandler::onTransactionCreate($Transaction);
        EventHandler::onTransactionStatusChange($Transaction);
        $Invoice->addTransaction($Transaction);

        $LinkedTransaction = TransactionFactory::createPaymentTransaction(
            1,
            $Invoice->getCurrency(),
            QUI\Utils\Uuid::get(),
            'phpunit',
            [],
            $SystemUser,
            false,
            $this->globalProcessId
        );
        $this->transactionIds[] = $LinkedTransaction->getTxId();
        $Invoice->linkTransaction($LinkedTransaction);
        $Invoice->linkTransaction($LinkedTransaction);

        $TransactionDraft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $DraftTransaction = TransactionFactory::createPaymentTransaction(
            2,
            $TransactionDraft->getCurrency(),
            $TransactionDraft->getUUID(),
            'phpunit',
            [],
            $SystemUser,
            false,
            $this->globalProcessId
        );
        $this->transactionIds[] = $DraftTransaction->getTxId();
        EventHandler::onTransactionCreate($DraftTransaction);
        EventHandler::onTransactionStatusChange($DraftTransaction);
        $TransactionDraft->addTransaction($DraftTransaction);
        $TransactionDraft->linkTransaction($LinkedTransaction);

        $TransactionDraft->setCustomer($User);
        $TransactionDraft->setAttribute('invoice_address_id', $Address->getUUID());
        $TransactionDraft->setAttribute('invoice_address', $Address->toJSON());
        $TransactionDraft->setAttribute('payment_method', -1);
        $TransactionDraft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $TransactionDraft->addArticle($this->createArticle('TEMP-COPY', 3));
        $TransactionDraft->save($SystemUser);

        $TemporaryCopy = $TransactionDraft->copy($SystemUser, true);
        $TemporaryCopy->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        $InvoiceFromTemporaryCopy = $TemporaryCopy->createInvoice($SystemUser);
        self::assertInstanceOf(Invoice::class, $InvoiceFromTemporaryCopy);

        $ArrayCustomerDraft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $ArrayCustomerDraft->setCustomer(null);
        self::assertFalse($ArrayCustomerDraft->getAttribute('customer_id'));
        $ArrayCustomerDraft->setCustomer([
            'id' => $User->getUUID(),
            'address' => $Address->getAttributes()
        ]);
        self::assertSame($User->getUUID(), $ArrayCustomerDraft->getAttribute('customer_id'));
        $ArrayCustomerDraft->setCustomer($ArrayCustomerDraft->getCustomer());

        $ArrayCustomerDraft->setCustomer([
            'id' => QUI\Utils\Uuid::get(),
            'address' => [
                'firstname' => 'Snapshot',
                'lastname' => 'Customer',
                'street_no' => 'Snapshot-Weg 6',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE'
            ]
        ]);

        $DeleteDraft = Factory::getInstance()->createInvoice($SystemUser, $this->globalProcessId);
        $Handler->delete($DeleteDraft->getUUID(), $SystemUser);

        self::assertNotEmpty(
            QUI\ERP\Accounting\Invoice\Utils\Invoice::getTransactionsByInvoice($Invoice)
        );
    }

    private function createArticle(string $articleNumber, float $price): Article
    {
        return new Article([
            'id' => 1,
            'articleNo' => $articleNumber,
            'title' => 'Lifecycle test article',
            'unitPrice' => $price,
            'quantity' => 1,
            'vat' => 19
        ]);
    }

    private function replaceSessionUser(UserInterface $User): ?UserInterface
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $Property->setAccessible(true);

        $PreviousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }
}
