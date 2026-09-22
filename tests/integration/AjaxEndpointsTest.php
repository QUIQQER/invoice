<?php

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\Integration;

use Closure;
use QUI;
use QUI\Ajax;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\Interfaces\Users\User;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionProperty;

class AjaxEndpointsTest extends SqliteIntegrationTestCase
{
    /** @var array<string, mixed> */
    private array $originalAjaxCallables;

    /** @var array<string, mixed> */
    private array $originalAjaxFunctions;

    /** @var array<string, mixed> */
    private array $originalAjaxPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalAjaxCallables = $this->ajaxState('callables');
        $this->originalAjaxFunctions = $this->ajaxState('functions');
        $this->originalAjaxPermissions = $this->ajaxState('permissions');

        $this->setAjaxState('callables', []);
        $this->setAjaxState('functions', []);
        $this->setAjaxState('permissions', []);
        $this->registerAjaxFiles();
    }

    protected function tearDown(): void
    {
        $this->setAjaxState('callables', $this->originalAjaxCallables);
        $this->setAjaxState('functions', $this->originalAjaxFunctions);
        $this->setAjaxState('permissions', $this->originalAjaxPermissions);

        parent::tearDown();
    }

    public function testProcessingStatusEndpointsPersistAndExposeCrudLifecycle(): void
    {
        $callables = Ajax::getRegisteredCallables();
        self::assertCount(45, $callables);

        $permissions = $this->ajaxState('permissions');
        self::assertSame(array_keys($callables), array_keys($permissions));

        $titles = [];
        foreach (QUI::availableLanguages() as $language) {
            $titles[$language] = 'Ajax status';
        }

        $Handler = ProcessingStatusHandler::getInstance();
        $statusId = $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_getNextId')() + 1000;

        try {
            $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_create')(
                $statusId,
                '#112233',
                json_encode($titles, JSON_THROW_ON_ERROR),
                json_encode([
                    ProcessingStatusHandler::STATUS_OPTION_PREVENT_INVOICE_POSTING => false
                ], JSON_THROW_ON_ERROR)
            );

            $created = $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_get')($statusId);
            self::assertSame($statusId, $created['id']);
            self::assertSame('#112233', $created['color']);
            self::assertGreaterThan($statusId, $this->endpoint(
                'package_quiqqer_invoice_ajax_processingStatus_getNextId'
            )());

            $list = $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_list')();
            self::assertSame(count($list['data']), $list['total']);
            self::assertContains($statusId, array_column($list['data'], 'id'));

            $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_update')(
                $statusId,
                '#332211',
                json_encode($titles, JSON_THROW_ON_ERROR),
                json_encode([
                    ProcessingStatusHandler::STATUS_OPTION_PREVENT_INVOICE_POSTING => true
                ], JSON_THROW_ON_ERROR)
            );
            $updated = $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_get')($statusId);
            self::assertSame('#332211', $updated['color']);
            self::assertTrue(
                $updated['options'][ProcessingStatusHandler::STATUS_OPTION_PREVENT_INVOICE_POSTING]
            );
        } finally {
            if (isset($Handler->getList()[$statusId])) {
                $this->endpoint('package_quiqqer_invoice_ajax_processingStatus_delete')($statusId);
            }
        }

        self::assertArrayNotHasKey($statusId, $Handler->getList());
    }

    public function testTemporaryInvoiceEndpointsSaveCalculateRenderCopyAndPost(): void
    {
        [$Customer, $Address] = $this->createCustomer('temporary');

        $this->endpoint('package_quiqqer_invoice_ajax_address_create')(
            $Customer->getUUID(),
            json_encode([
                'firstname' => 'Second',
                'lastname' => 'Address',
                'street_no' => 'Endpoint Street 2',
                'zip' => '20000',
                'city' => 'Hamburg',
                'country' => 'DE'
            ], JSON_THROW_ON_ERROR)
        );
        self::assertNotEmpty($Customer->getAttribute('quiqqer.erp.address'));

        $invoiceUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_create')();
        $saved = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_save')(
            $invoiceUuid,
            json_encode([
                'customer_id' => $Customer->getUUID(),
                'invoice_address_id' => $Address->getUUID(),
                'invoice_address' => $Address->toJSON(),
                'addressDelivery' => [
                    'firstname' => 'Delivery',
                    'lastname' => 'Recipient',
                    'street_no' => 'Delivery Street 3',
                    'zip' => '10115',
                    'city' => 'Berlin',
                    'country' => 'DE'
                ],
                'payment_method' => -1,
                'currency' => 'EUR',
                'currencyRate' => 1.0,
                'project_name' => 'Ajax endpoint invoice',
                'articles' => [
                    'articles' => [$this->articleData('AJAX-TEMP-1')]
                ]
            ], JSON_THROW_ON_ERROR)
        );
        self::assertSame('Ajax endpoint invoice', $saved['project_name']);
        self::assertCount(1, $saved['articles']['articles']);

        $temporary = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_get')($invoiceUuid);
        self::assertSame($Customer->getUUID(), $temporary['customer_id']);
        self::assertSame(1.0, $temporary['currencyRate']);
        self::assertIsArray($temporary['service_period']);

        $calculation = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_calc')(
            json_encode([$this->articleData('AJAX-CALC-1')], JSON_THROW_ON_ERROR),
            '{}'
        );
        self::assertGreaterThan(0, $calculation['sum']);
        self::assertIsBool($this->endpoint(
            'package_quiqqer_invoice_ajax_invoices_temporary_isNetto'
        )($Customer->getUUID()));
        self::assertSame(14, $this->endpoint(
            'package_quiqqer_invoice_ajax_invoices_temporary_getTimeForPayment'
        )($Customer->getId()));

        $missing = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_missing')($invoiceUuid);
        self::assertIsArray($missing);

        $grid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_list')(
            json_encode(['page' => 1, 'perPage' => 20], JSON_THROW_ON_ERROR),
            '{}'
        );
        self::assertSame(1, $grid['total']);
        self::assertSame($invoiceUuid, $grid['data'][0]['id']);

        $htmlData = json_encode([
            'project_name' => 'Ajax rendered invoice',
            'articles' => [
                'articles' => [$this->articleData('AJAX-HTML-1')]
            ]
        ], JSON_THROW_ON_ERROR);
        self::assertStringContainsString(
            'AJAX-TEMP-1',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_html')($invoiceUuid, $htmlData)
        );
        self::assertNotSame(
            '',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_previewhtml')(
                $invoiceUuid,
                $htmlData
            )
        );
        self::assertNotSame(
            '',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_getArticleHtml')($invoiceUuid)
        );

        $Draft = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString($invoiceUuid);
        $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_lock')($Draft->getId());
        self::assertSame($invoiceUuid, $Draft->getUUID());
        $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_unlock')($invoiceUuid);
        self::assertFalse($Draft->isLocked());

        $copyUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_copy')($invoiceUuid);
        self::assertNotSame($invoiceUuid, $copyUuid);
        $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_delete')($copyUuid);
        self::assertSame(
            1,
            (int)$this->connection->fetchOne(
                'SELECT COUNT(*) FROM ' . Handler::getInstance()->temporaryInvoiceTable()
            )
        );

        $postedUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_temporary_post')($invoiceUuid);
        self::assertInstanceOf(Invoice::class, Handler::getInstance()->getInvoiceByHash($postedUuid));
    }

    public function testPostedInvoiceEndpointsExposeSearchHistoryCopiesAndReversals(): void
    {
        [$Customer, $Address] = $this->createCustomer('posted');
        $Draft = QUI\ERP\Accounting\Invoice\Factory::getInstance()->createInvoice();
        $Draft->setCustomer($Customer);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(
            QUI\ERP\Accounting\Invoice\InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL,
            1
        );
        $Draft->importArticles(['articles' => [$this->articleData('AJAX-POSTED-1')]]);
        $Invoice = $Draft->post();
        $invoiceUuid = $Invoice->getUUID();

        $this->endpoint('package_quiqqer_invoice_ajax_invoices_addComment')(
            $invoiceUuid,
            'Ajax endpoint comment'
        );
        self::assertStringContainsString(
            'Ajax endpoint comment',
            json_encode($this->endpoint('package_quiqqer_invoice_ajax_invoices_getHistory')($invoiceUuid))
        );
        self::assertSame(
            [],
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_getTransactions')($invoiceUuid)
        );
        self::assertFalse($this->endpoint('package_quiqqer_invoice_ajax_invoices_hasRefund')($invoiceUuid));

        $invoiceData = $this->endpoint('package_quiqqer_invoice_ajax_invoices_get')($invoiceUuid);
        self::assertSame($invoiceUuid, $invoiceData['uuid']);
        self::assertStringContainsString(
            'AJAX-POSTED-1',
            json_encode($invoiceData['articles'], JSON_THROW_ON_ERROR)
        );
        self::assertNotSame(
            '',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_articleHtml')($invoiceUuid)
        );
        self::assertNotSame(
            '',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_getArticleHtml')($invoiceUuid)
        );
        self::assertNotSame(
            '',
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_preview')($invoiceUuid, 1)
        );

        $this->endpoint('package_quiqqer_invoice_ajax_invoices_setCustomerFiles')($invoiceUuid, '[]');
        self::assertSame([], Handler::getInstance()->getInvoiceByHash($invoiceUuid)->getCustomerFiles());

        $statusId = (int)array_key_first(ProcessingStatusHandler::getInstance()->getList());
        $this->endpoint('package_quiqqer_invoice_ajax_invoices_setStatus')($invoiceUuid, $statusId);
        self::assertSame(
            $statusId,
            (int)$this->connection->fetchOne(
                'SELECT processing_status FROM ' . Handler::getInstance()->invoiceTable() . ' WHERE hash = ?',
                [$invoiceUuid]
            )
        );

        $copyUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_copy')($invoiceUuid);
        self::assertNotSame($invoiceUuid, $copyUuid);

        $list = $this->endpoint('package_quiqqer_invoice_ajax_invoices_list')(
            json_encode(['page' => 1, 'perPage' => 20], JSON_THROW_ON_ERROR)
        );
        self::assertNotEmpty($list);

        $search = $this->endpoint('package_quiqqer_invoice_ajax_invoices_search')(
            json_encode([
                'page' => 1,
                'perPage' => 20,
                'sortOn' => 'date',
                'sortBy' => 'DESC',
                'calcTotal' => true
            ], JSON_THROW_ON_ERROR),
            json_encode(['search' => $Invoice->getPrefixedNumber()], JSON_THROW_ON_ERROR)
        );
        self::assertGreaterThanOrEqual(1, $search['total']);

        self::assertIsArray($this->endpoint('package_quiqqer_invoice_ajax_invoices_settings_templates')());
        self::assertSame(
            QUI\ERP\Accounting\Invoice\Settings::getInstance()->get('invoice', 'sendMailAtCreation'),
            $this->endpoint('package_quiqqer_invoice_ajax_invoices_setting')(
                'invoice',
                'sendMailAtCreation'
            )
        );
        $categories = $this->endpoint(
            'package_quiqqer_invoice_ajax_invoices_panel_getCategories'
        )();
        self::assertIsArray($categories);
        self::assertIsString($this->endpoint(
            'package_quiqqer_invoice_ajax_invoices_panel_getCategory'
        )(array_key_first($categories) ?? 'invoices'));

        $creditNoteUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_createCreditNote')(
            $invoiceUuid,
            json_encode(['source' => 'ajax-endpoint-test'], JSON_THROW_ON_ERROR)
        );
        self::assertNotSame($invoiceUuid, $creditNoteUuid);

        $reversalUuid = $this->endpoint('package_quiqqer_invoice_ajax_invoices_reversal')(
            $invoiceUuid,
            'Ajax reversal test'
        );
        self::assertNotSame($invoiceUuid, $reversalUuid);
    }

    private function endpoint(string $name): Closure
    {
        $callables = Ajax::getRegisteredCallables();
        self::assertArrayHasKey($name, $callables);
        self::assertInstanceOf(Closure::class, $callables[$name]['callable']);

        return $callables[$name]['callable'];
    }

    /** @return array{User, QUI\Users\Address} */
    private function createCustomer(string $suffix): array
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $username = 'invoice-ajax-' . $suffix . '-' . uniqid();
        $Customer = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Ajax',
            'lastname' => ucfirst($suffix),
            'customerId' => 'AJAX-' . strtoupper($suffix)
        ], $SystemUser);
        $Address = $Customer->addAddress([
            'firstname' => 'Ajax',
            'lastname' => ucfirst($suffix),
            'street_no' => 'Endpoint Street 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);
        self::assertNotNull($Address);
        $Customer->setAttribute('address', $Address->getUUID());
        $Customer->save($SystemUser);
        QUI::getPermissionManager()->setPermissions(
            $Customer,
            ['quiqqer.invoice.timeForPayment' => 14],
            $SystemUser
        );

        return [$Customer, $Address];
    }

    /** @return array<string, int|float|string> */
    private function articleData(string $articleNumber): array
    {
        return [
            'id' => 1,
            'articleNo' => $articleNumber,
            'title' => 'Ajax endpoint article',
            'description' => 'Observed through a registered Ajax callback',
            'unitPrice' => 10,
            'quantity' => 2,
            'vat' => 19
        ];
    }

    private function registerAjaxFiles(): void
    {
        $files = [];
        $Iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(dirname(__DIR__, 2) . '/ajax')
        );

        foreach ($Iterator as $File) {
            if ($File->isFile() && $File->getExtension() === 'php') {
                $files[] = $File->getPathname();
            }
        }

        sort($files);

        foreach ($files as $file) {
            require $file;
        }
    }

    /** @return array<string, mixed> */
    private function ajaxState(string $property): array
    {
        return (new ReflectionProperty(Ajax::class, $property))->getValue();
    }

    /** @param array<string, mixed> $value */
    private function setAjaxState(string $property, array $value): void
    {
        (new ReflectionProperty(Ajax::class, $property))->setValue(null, $value);
    }
}
