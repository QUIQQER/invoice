<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use QUI;
use QUI\ERP\Accounting\ArticleList;
use QUI\ERP\Accounting\Calc;
use QUI\ERP\Accounting\Invoice\Factory;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Offers\OfferTemporary;
use QUI\ERP\Enums\Accounting\CalculationMode;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use ReflectionProperty;

class ManualCalculationSnapshotTest extends SqliteIntegrationTestCase
{
    private mixed $offerConfigPath;
    private mixed $offerConfig;
    private string $isolatedOfferConfig;
    private bool $transactionStarted = false;

    protected function setUp(): void
    {
        parent::setUp();
        QUI\Update::importDatabase(OPT_DIR . 'quiqqer/offers/database.xml');
        QUI\Update::importPermissions(OPT_DIR . 'quiqqer/offers/permissions.xml', 'quiqqer/offers');
        $Package = QUI::getPackage('quiqqer/offers');
        $Package->getConfig();
        $this->offerConfigPath = (new ReflectionProperty($Package, 'configPath'))->getValue($Package);
        $this->offerConfig = (new ReflectionProperty($Package, 'Config'))->getValue($Package);
        $path = tempnam(sys_get_temp_dir(), 'manual-offer-config-');
        self::assertNotFalse($path);
        self::assertTrue(copy($this->offerConfigPath, $path));
        $this->isolatedOfferConfig = $path;
        (new ReflectionProperty($Package, 'configPath'))->setValue($Package, $path);
        (new ReflectionProperty($Package, 'Config'))->setValue($Package, null);
        QUI\ERP\Accounting\Offers\Settings::getInstance()->set('offers', 'sendMailAtCreation', false);

        // Use the base class's SQLite or CI connection and discard all test fixtures afterwards.
        $this->connection->beginTransaction();
        $this->transactionStarted = true;
    }

    protected function tearDown(): void
    {
        try {
            if ($this->transactionStarted) {
                $this->connection->rollBack();
            }
        } finally {
            if (isset($this->isolatedOfferConfig)) {
                $Package = QUI::getPackage('quiqqer/offers');
                (new ReflectionProperty($Package, 'configPath'))->setValue($Package, $this->offerConfigPath);
                (new ReflectionProperty($Package, 'Config'))->setValue($Package, $this->offerConfig);
                unlink($this->isolatedOfferConfig);
            }

            parent::tearDown();
        }
    }

    public static function draftClasses(): iterable
    {
        yield 'invoice' => [InvoiceTemporary::class];
        yield 'offer' => [OfferTemporary::class];
    }

    #[DataProvider('draftClasses')]
    public function testManualImportPreservesOriginalAmountsAndFactors(string $class): void
    {
        $Draft = $this->draft($class);
        $snapshot = $this->snapshot();
        $Draft->importArticles($snapshot);
        $Draft->getArticles()->recalculate();

        self::assertSame(CalculationMode::MANUAL, $Draft->getArticles()->getCalculationMode());
        self::assertSame(9.99012345, $Draft->getArticles()->getCalculations()['sum']);
        self::assertSame(8.395, $Draft->getArticles()->getCalculations()['subSum']);
        self::assertSame(1.5951, $Draft->getArticles()->getCalculations()['vatArray'][19]['sum']);
        self::assertSame(0.9876, $Draft->getArticles()->getPriceFactors()->getFactor(0)->getNettoSum());
        self::assertSame(9.9901, $Draft->getArticles()->getArticles()[0]->toArray()['calculated']['bruttoCalculated']['sum']);
    }

    #[DataProvider('draftClasses')]
    public function testAutomaticAndLegacyImportsKeepTheHistoricalPositionCaches(string $class): void
    {
        foreach (['snapshot', 'legacy'] as $format) {
            $Draft = $this->draft($class);
            $snapshot = $this->snapshot();
            unset($snapshot['calculationMode']);
            $Draft->importArticles($format === 'snapshot' ? $snapshot : $snapshot['articles']);
            self::assertSame(CalculationMode::AUTOMATIC, $Draft->getArticles()->getCalculationMode());
            self::assertSame([], $Draft->getArticles()->getCalculations());
            $Article = $Draft->getArticles()->getArticles()[0];
            self::assertTrue((new ReflectionProperty($Article, 'calculated'))->getValue($Article));
            self::assertSame(8.395, $Article->toArray()['calculated']['nettoSum']);
            self::assertSame(0, $Draft->getArticles()->getPriceFactors()->count());
            $totals = $snapshot['calculations'];
            $totals['sum'] = 123.45;
            $Calc = $this->createMock(Calc::class);
            $Calc->expects(self::once())->method('calcArticleList')->willReturnCallback(
                static function (ArticleList $List, callable $callback) use ($totals): ArticleList {
                    $callback($totals);
                    return $List;
                }
            );
            $Draft->getArticles()->calc($Calc);
            self::assertSame(123.45, $Draft->getArticles()->getCalculations()['sum']);
        }
    }

    public function testInvoiceSaveReloadPostCopyAndReversalKeepTheManualSnapshot(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $Draft = $this->invoiceDraft();
        $Draft->importArticles($this->snapshot());
        $Draft->save($SystemUser);
        $Reloaded = Handler::getInstance()->getTemporaryInvoice($Draft->getUUID());
        self::assertSame(CalculationMode::MANUAL, $Reloaded->getArticles()->getCalculationMode());
        self::assertEqualsWithDelta(9.99012345, $Reloaded->getAttribute('sum'), 0.000000001);
        self::assertEqualsWithDelta(8.395, $Reloaded->getAttribute('nettosubsum'), 0.000000001);
        $DraftCopy = $Reloaded->copy($SystemUser);
        self::assertSame(9.99012345, $DraftCopy->getArticles()->getCalculations()['sum']);
        $Invoice = $Reloaded->post($SystemUser);
        self::assertEqualsWithDelta(9.99012345, $Invoice->getAttribute('sum'), 0.000000001);
        self::assertSame(9.99012345, $Invoice->getArticles()->getCalculations()['sum']);
        $Copy = $Invoice->copy($SystemUser);
        self::assertSame(CalculationMode::MANUAL, $Copy->getArticles()->getCalculationMode());
        self::assertSame(9.99012345, $Copy->getArticles()->getCalculations()['sum']);
        $Reversal = $Invoice->createReversal($SystemUser);
        self::assertSame(CalculationMode::MANUAL, $Reversal->getArticles()->getCalculationMode());
        self::assertSame(-9.99012345, $Reversal->getArticles()->getCalculations()['sum']);
        self::assertSame(-1.5951, $Reversal->getArticles()->getCalculations()['vatArray'][19]['sum']);
        self::assertSame(-9.9901, $Reversal->getArticles()->getArticles()[0]->toArray()['calculated']['bruttoCalculated']['sum']);
        self::assertSame(9.99012345, $Invoice->getArticles()->getCalculations()['sum']);
        $CreditNote = $Invoice->createCreditNote($SystemUser);
        self::assertSame(-9.99012345, $CreditNote->getArticles()->getCalculations()['sum']);
    }

    public function testOfferSaveReloadPostCopyAndInvoiceTransitionPreserveTheManualSnapshot(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $InvoiceDraft = $this->invoiceDraft();
        $Draft = QUI\ERP\Accounting\Offers\Factory::getInstance()->createOffer($SystemUser);
        $Draft->setCustomer($InvoiceDraft->getCustomer());
        $Draft->setAttribute('customer_address', $InvoiceDraft->getAttribute('invoice_address'));
        $Draft->setAttribute('payment_method', -1);
        $Draft->importArticles($this->snapshot());
        $Draft->save($SystemUser);
        $Reloaded = QUI\ERP\Accounting\Offers\Handler::getInstance()->getTemporaryOffer($Draft->getUUID());
        self::assertEqualsWithDelta(9.99012345, $Reloaded->getAttribute('sum'), 0.000000001);
        self::assertEqualsWithDelta(8.395, $Reloaded->getAttribute('nettosubsum'), 0.000000001);
        self::assertSame(CalculationMode::MANUAL, $Reloaded->getArticles()->getCalculationMode());
        $DraftCopy = $Reloaded->copy($SystemUser);
        self::assertSame(9.99012345, $DraftCopy->getArticles()->getCalculations()['sum']);
        $Offer = $Reloaded->createOffer($SystemUser);
        self::assertEqualsWithDelta(9.99012345, $Offer->getAttribute('sum'), 0.000000001);
        self::assertSame(9.99012345, $Offer->getArticles()->getCalculations()['sum']);
        $Copy = $Offer->copy($SystemUser);
        self::assertSame(CalculationMode::MANUAL, $Copy->getArticles()->getCalculationMode());
        self::assertSame(9.99012345, $Copy->getArticles()->getCalculations()['sum']);
        $Invoice = $Offer->createInvoice($SystemUser);
        self::assertSame(CalculationMode::MANUAL, $Invoice->getArticles()->getCalculationMode());
        self::assertSame(9.99012345, $Invoice->getArticles()->getCalculations()['sum']);
        self::assertSame(0.9876, $Invoice->getArticles()->getPriceFactors()->getFactor(0)->getNettoSum());
    }

    public function testManualPriceDisplaySurvivesCustomerDefaultsInBothDocumentConventions(): void
    {
        $SystemUser = QUI::getUsers()->getSystemUser();

        foreach ([true, false] as $isNetto) {
            $InvoiceDraft = $this->invoiceDraft();
            $snapshot = $this->snapshot();
            $snapshot['calculations']['isNetto'] = $isNetto;
            $snapshot['articles'][0]['calculated']['isNetto'] = $isNetto;
            $InvoiceDraft->importArticles($snapshot);
            $InvoiceDraft->save($SystemUser);
            $ReloadedInvoice = Handler::getInstance()->getTemporaryInvoice($InvoiceDraft->getUUID());
            self::assertSame($isNetto ? 0 : 1, $ReloadedInvoice->getAttribute('isbrutto'));
            $Posted = $ReloadedInvoice->post($SystemUser);
            self::assertSame($isNetto ? 0 : 1, (int)$Posted->getAttribute('isbrutto'));
            self::assertSame($isNetto, $Posted->getArticles()->getCalculations()['isNetto']);

            $OfferDraft = QUI\ERP\Accounting\Offers\Factory::getInstance()->createOffer($SystemUser);
            $OfferDraft->setCustomer($InvoiceDraft->getCustomer());
            $OfferDraft->setAttribute('customer_address', $InvoiceDraft->getAttribute('invoice_address'));
            $OfferDraft->setAttribute('payment_method', -1);
            $OfferDraft->importArticles($snapshot);
            $OfferDraft->save($SystemUser);
            $ReloadedOffer = QUI\ERP\Accounting\Offers\Handler::getInstance()->getTemporaryOffer(
                $OfferDraft->getUUID()
            );
            self::assertSame($isNetto ? 1 : 2, (int)$ReloadedOffer->getAttribute('isbrutto'));
            $PostedOffer = $ReloadedOffer->createOffer($SystemUser);
            self::assertSame($isNetto ? 1 : 2, (int)$PostedOffer->getAttribute('isbrutto'));
            self::assertSame($isNetto, $PostedOffer->getArticles()->getCalculations()['isNetto']);
        }
    }

    public function testSaveEndpointsKeepSnapshotFactorsAndCalcEndpointsRespectThePolicy(): void
    {
        $Callables = new ReflectionProperty(QUI\Ajax::class, 'callables');
        $Permissions = new ReflectionProperty(QUI\Ajax::class, 'permissions');
        $previousCallables = $Callables->getValue();
        $previousPermissions = $Permissions->getValue();

        try {
            foreach (['invoice', 'offers'] as $module) {
                $InvoiceDraft = $this->invoiceDraft();
                $Draft = $InvoiceDraft;

                if ($module === 'offers') {
                    $Draft = QUI\ERP\Accounting\Offers\Factory::getInstance()->createOffer(
                        QUI::getUsers()->getSystemUser()
                    );
                    $Draft->setCustomer($InvoiceDraft->getCustomer());
                    $Draft->setAttribute('customer_address', $InvoiceDraft->getAttribute('invoice_address'));
                    $Draft->setAttribute('payment_method', -1);
                }

                $prefix = $module === 'invoice' ? 'invoices' : 'offers';
                $root = OPT_DIR . 'quiqqer/' . $module . '/ajax/' . $prefix . '/temporary/';
                require $root . 'save.php';
                require $root . 'calc.php';
                $registered = QUI\Ajax::getRegisteredCallables();
                $name = 'package_quiqqer_' . $module . '_ajax_' . $prefix . '_temporary_';
                $save = $registered[$name . 'save']['callable'];
                $calc = $registered[$name . 'calc']['callable'];
                $payload = [
                    'customer_id' => $Draft->getAttribute('customer_id'),
                    'invoice_address_id' => $InvoiceDraft->getAttribute('invoice_address_id'),
                    'invoice_address' => $InvoiceDraft->getAttribute('invoice_address'),
                    'customer_address' => $InvoiceDraft->getAttribute('invoice_address'),
                    'articles' => $this->snapshot(),
                    'priceFactors' => [],
                    'currency' => 'EUR'
                ];
                $result = $save($Draft->getUUID(), json_encode($payload));
                self::assertIsArray($result);
                self::assertSame('manual', $result['articles']['calculationMode']);
                self::assertSame(9.99012345, $result['articles']['calculations']['sum']);
                self::assertCount(1, $result['articles']['priceFactors']);
                self::assertSame(0.9876, $result['articles']['priceFactors'][0]['nettoSum']);
                $manual = $calc(json_encode($this->snapshot()), '{}');
                self::assertSame(9.99012345, $manual['sum']);
                $automatic = $this->snapshot();
                unset($automatic['calculationMode']);
                $automatic['calculations']['sum'] = 99999;
                $automatic['articles'][0]['calculated']['sum'] = 99999;
                $user = '{"country":"DE","isCompany":true}';
                $legacy = $calc(json_encode($automatic['articles']), $user);
                $wrapped = $calc(json_encode($automatic), $user);
                self::assertSame($legacy, $wrapped);
            }
        } finally {
            $Callables->setValue(null, $previousCallables);
            $Permissions->setValue(null, $previousPermissions);
        }
    }

    private function invoiceDraft(): InvoiceTemporary
    {
        $SystemUser = QUI::getUsers()->getSystemUser();
        $username = 'manual-snapshot-' . uniqid();
        $Customer = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'Manual',
            'lastname' => 'Customer'
        ], $SystemUser);
        $Address = $Customer->addAddress([
            'firstname' => 'Manual',
            'lastname' => 'Customer',
            'street_no' => 'Testweg 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE'
        ], $SystemUser);
        $Draft = Factory::getInstance()->createInvoice($SystemUser);
        $Draft->setCustomer($Customer);
        $Draft->setAttribute('invoice_address_id', $Address->getUUID());
        $Draft->setAttribute('invoice_address', $Address->toJSON());
        $Draft->setAttribute('payment_method', -1);
        $Draft->setAttribute(InvoiceTemporary::SPECIAL_ATTRIBUTE_DO_NOT_SEND_CREATION_MAIL, 1);
        return $Draft;
    }

    private function draft(string $class): InvoiceTemporary|OfferTemporary
    {
        $Draft = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods(['getCurrency'])->getMock();
        $Draft->method('getCurrency')->willReturn(QUI\ERP\Defaults::getCurrency());
        (new ReflectionProperty($Draft, 'Articles'))->setValue($Draft, new ArticleList());
        return $Draft;
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'calculationMode' => 'manual',
            'articles' => [[
                'title' => 'Original amount',
                'quantity' => 1,
                'unitPrice' => 8.395,
                'vat' => 19,
                'calculated' => [
                    'price' => 8.395,
                    'basisPrice' => 8.395,
                    'nettoPriceNotRounded' => 8.395,
                    'sum' => 8.395,
                    'nettoPrice' => 8.395,
                    'nettoBasisPrice' => 8.395,
                    'nettoSubSum' => 8.395,
                    'nettoSum' => 8.395,
                    'vatArray' => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%'],
                    'isNetto' => true,
                    'isEuVat' => false,
                    'bruttoCalculated' => [
                        'sum' => 9.9901,
                        'quantity_sum' => 9.9901,
                        'unitPrice' => 9.9901
                    ]
                ]
            ]],
            'calculations' => [
                'sum' => 9.99012345,
                'subSum' => 8.395,
                'grandSubSum' => 9.9901,
                'nettoSubSum' => 8.395,
                'nettoSum' => 8.395,
                'vatSum' => 1.5951,
                'vatArray' => [19 => ['vat' => 19, 'sum' => 1.5951, 'text' => 'VAT 19%']],
                'vatText' => [19 => 'VAT 19%'],
                'isNetto' => true,
                'isEuVat' => false,
                'currencyData' => QUI\ERP\Defaults::getCurrency()->toArray()
            ],
            'priceFactors' => [[
                'title' => 'Original factor',
                'description' => '',
                'sum' => 1.2345,
                'sumFormatted' => '',
                'nettoSum' => 0.9876,
                'nettoSumFormatted' => '',
                'visible' => 1,
                'value' => 0.2469,
                'vat' => 19
            ]]
        ];
    }
}
