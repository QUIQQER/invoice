<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Controls\Sitemap\Item;
use QUI\Controls\Sitemap\Map;
use QUI\ERP\Accounting\Invoice\ErpProvider;
use QUI\ERP\Accounting\Invoice\Articles\Article as InvoiceArticle;
use QUI\ERP\Accounting\Invoice\Articles\Text as InvoiceText;
use QUI\ERP\Accounting\Invoice\Exception as InvoiceException;
use QUI\ERP\Accounting\Invoice\NumberRanges;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCancelled;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderCreditNote;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Accounting\Invoice\PaymentReceiver;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Exception as ProcessingStatusException;
use QUI\ERP\Accounting\Invoice\Utils\Panel;

class SimpleClassesUnitTest extends TestCase
{
    public function testProviderIdentifiersAndTitles(): void
    {
        $Locale = QUI::getLocale();

        self::assertSame('Invoice', PaymentReceiver::getType());
        self::assertIsString(PaymentReceiver::getTypeTitle($Locale));
        self::assertSame('Invoice', OutputProviderInvoice::getEntityType());
        self::assertIsString(OutputProviderInvoice::getEntityTypeTitle($Locale));
        self::assertSame('Canceled', OutputProviderCancelled::getEntityType());
        self::assertIsString(OutputProviderCancelled::getEntityTypeTitle($Locale));
        self::assertSame('CreditNote', OutputProviderCreditNote::getEntityType());
        self::assertIsString(OutputProviderCreditNote::getEntityTypeTitle($Locale));
    }

    public function testErpProviderDefinitions(): void
    {
        $ranges = ErpProvider::getNumberRanges();

        self::assertCount(2, $ranges);
        self::assertInstanceOf(NumberRanges\Invoice::class, $ranges[0]);
        self::assertInstanceOf(NumberRanges\TemporaryInvoice::class, $ranges[1]);

        $mailLocale = ErpProvider::getMailLocale();
        self::assertCount(3, $mailLocale);

        foreach ($mailLocale as $definition) {
            self::assertArrayHasKey('title', $definition);
            self::assertArrayHasKey('description', $definition);
            self::assertArrayHasKey('subject', $definition);
            self::assertArrayHasKey('content', $definition);
            self::assertArrayHasKey('subject.description', $definition);
            self::assertArrayHasKey('content.description', $definition);
        }
    }

    public function testErpProviderAddsMenuItems(): void
    {
        $Map = new Map();
        ErpProvider::addMenuItems($Map);

        $Accounting = $Map->getChildrenByName('accounting');
        self::assertNotNull($Accounting);
        self::assertSame('invoice', $Accounting->toArray()['items'][0]['name']);
        self::assertCount(3, $Accounting->toArray()['items'][0]['items']);

        $ExistingMap = new Map();
        $ExistingAccounting = new Item(['name' => 'accounting']);
        $ExistingMap->appendChild($ExistingAccounting);
        ErpProvider::addMenuItems($ExistingMap);

        self::assertSame($ExistingAccounting, $ExistingMap->getChildrenByName('accounting'));
        self::assertSame('invoice', $ExistingAccounting->toArray()['items'][0]['name']);
    }

    public function testNumberRangeTitles(): void
    {
        $Locale = QUI::getLocale();

        self::assertIsString((new NumberRanges\Invoice())->getTitle($Locale));
        self::assertIsString((new NumberRanges\TemporaryInvoice())->getTitle($Locale));
    }

    public function testPackageExceptionsCanBeCreated(): void
    {
        $exceptions = [
            new InvoiceException('invoice'),
            new ProcessingStatusException('processing-status')
        ];

        foreach ($exceptions as $Exception) {
            self::assertInstanceOf(QUI\Exception::class, $Exception);
            self::assertNotSame('', $Exception->getMessage());
        }
    }

    public function testInvoiceArticleTypesExposeTheirControls(): void
    {
        $Article = new InvoiceArticle([
            'title' => 'Article',
            'quantity' => 1,
            'unitPrice' => 10,
            'vat' => 19
        ]);
        $Text = new InvoiceText(['title' => 'Information']);

        self::assertSame(InvoiceArticle::class, $Article->toArray()['class']);
        self::assertStringContainsString('/Article', $Article->toArray()['control']);
        self::assertSame(InvoiceText::class, $Text->toArray()['class']);
        self::assertStringContainsString('/Text', $Text->toArray()['control']);
    }

    public function testPanelMetadataCanBeRead(): void
    {
        self::assertIsArray(Panel::getInvoicePackages());
        self::assertIsArray(Panel::getPanelCategories());
        self::assertIsString(Panel::getPanelCategory('phpunit-category-that-does-not-exist'));
    }
}
