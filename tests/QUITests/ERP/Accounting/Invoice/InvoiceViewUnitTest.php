<?php

namespace QUITests\ERP\Accounting\Invoice;

require_once __DIR__ . '/MailOutputFake.php';

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\ArticleListUnique;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\InvoiceView;
use QUI\ERP\Accounting\Invoice\Payment;

class InvoiceViewUnitTest extends TestCase
{
    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testPdfUsesDistinctUuidsForInvoiceAndDraftWithTheSameNumericId(): void
    {
        self::assertFalse(class_exists(QUI\ERP\Output\Output::class, false));
        class_alias(MailOutputFake::class, QUI\ERP\Output\Output::class);

        foreach ([Invoice::class, InvoiceTemporary::class] as $class) {
            $Invoice = $this->createMock($class);
            $Invoice->method('getCleanId')->willReturn(42);
            $Invoice->method('getUUID')->willReturn('uuid-' . $class);
            $Invoice->method('getInvoiceType')->willReturn(QUI\ERP\Constants::TYPE_INVOICE);

            self::assertInstanceOf(QUI\HtmlToPdf\Document::class, (new InvoiceView($Invoice))->toPDF());
        }

        self::assertSame([
            ['uuid-' . Invoice::class, 'Invoice'],
            ['uuid-' . InvoiceTemporary::class, 'Invoice']
        ], MailOutputFake::$calls);
    }

    public function testViewDelegatesInvoiceData(): void
    {
        $Articles = new ArticleListUnique([
            'articles' => [],
            'calculations' => []
        ]);
        $Currency = QUI\ERP\Defaults::getCurrency();
        $Customer = new QUI\ERP\User([
            'id' => 1,
            'username' => 'coverage-customer',
            'firstname' => 'Coverage',
            'lastname' => 'Customer'
        ]);
        $Payment = new Payment();

        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getArticles')->willReturn($Articles);
        $Invoice->method('getPrefixedNumber')->willReturn('INV-42');
        $Invoice->method('getUUID')->willReturn('invoice-uuid');
        $Invoice->method('getCurrency')->willReturn($Currency);
        $Invoice->method('getCustomer')->willReturn($Customer);
        $Invoice->method('getAttribute')->with('date')->willReturn('2026-07-17 10:00:00');
        $Invoice->method('getPaidStatusInformation')->willReturn(['paid' => 10]);
        $Invoice->method('getPayment')->willReturn($Payment);
        $Invoice->method('isPaid')->willReturn(true);

        $View = new InvoiceView($Invoice);

        self::assertSame($Articles, $View->getArticles());
        self::assertSame('INV-42', $View->getId());
        self::assertSame('invoice-uuid', $View->getHash());
        self::assertSame($Currency, $View->getCurrency());
        self::assertSame($Customer, $View->getCustomer());
        self::assertIsString($View->getDate(QUI::getLocale()));
        self::assertIsString($View->formatDate('2026-07-17', QUI::getLocale()));
        self::assertSame(['paid' => 10], $View->getPaidStatusInformation());
        self::assertSame($Payment, $View->getPayment());
        self::assertSame($Invoice, $View->getInvoice());
        self::assertTrue($View->isPaid());
        self::assertFalse($View->isDraft());
    }

    public function testViewUsesFallbacksWhenInvoiceAccessFails(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getArticles')->willThrowException(new QUI\Exception('articles'));
        $Invoice->method('getCurrency')->willThrowException(new QUI\Exception('currency'));
        $Invoice->method('getCustomer')->willThrowException(new QUI\Exception('customer'));
        $Invoice->method('getPaidStatusInformation')->willThrowException(new QUI\Exception('paid'));

        $View = new InvoiceView($Invoice);

        self::assertInstanceOf(ArticleListUnique::class, $View->getArticles());
        self::assertSame(QUI\ERP\Defaults::getCurrency(), $View->getCurrency());
        self::assertNull($View->getCustomer());
        self::assertSame([], $View->getPaidStatusInformation());
    }
}
