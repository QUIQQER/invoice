<?php

namespace QUITests\ERP\Accounting\Invoice\Utils;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

class InvoiceUnitTest extends TestCase
{
    public function testMissingAddressData(): void
    {
        self::assertSame([], InvoiceUtils::getMissingAddressData([
            'lastname' => 'Customer',
            'street_no' => 'Street 1',
            'city' => 'City',
            'country' => 'DE'
        ]));

        self::assertSame([], InvoiceUtils::getMissingAddressData([
            'company' => 'Company',
            'street_no' => 'Street 1',
            'city' => 'City',
            'country' => 'DE'
        ]));

        self::assertSame([
            'invoice_address_lastname',
            'invoice_address_street_no',
            'invoice_address_city',
            'invoice_address_country'
        ], InvoiceUtils::getMissingAddressData([]));
    }

    #[DataProvider('knownMissingAttributeProvider')]
    public function testKnownMissingAttributeMessages(string $attribute): void
    {
        self::assertIsString(InvoiceUtils::getMissingAttributeMessage($attribute));
    }

    public static function knownMissingAttributeProvider(): iterable
    {
        foreach (
            [
            'customer_id',
            'article',
            'payment',
            'invoice_address_id',
            'invoice_address_firstname',
            'invoice_address_lastname',
            'invoice_address_street_no',
            'invoice_address_zip',
            'invoice_address_city',
            'invoice_address_country',
            'status_prevents_posting'
            ] as $attribute
        ) {
            yield $attribute => [$attribute];
        }
    }

    public function testUnknownMissingAttributeThrows(): void
    {
        $this->expectException(Exception::class);
        InvoiceUtils::getMissingAttributeMessage('unknown');
    }

    public function testFormatArticlesArrayAddsDisplayValues(): void
    {
        $articles = [
            'calculations' => [
                'currencyData' => QUI\ERP\Defaults::getCurrency()->toArray()
            ],
            'articles' => [
                ['unitPrice' => 12.5, 'sum' => 25]
            ]
        ];

        $formatted = InvoiceUtils::formatArticlesArray($articles);
        self::assertIsArray($formatted);
        self::assertArrayHasKey('display_unitPrice', $formatted['articles'][0]);
        self::assertArrayHasKey('display_sum', $formatted['articles'][0]);

        $json = InvoiceUtils::formatArticlesArray(json_encode($articles));
        self::assertIsString($json);
        self::assertArrayHasKey('display_sum', json_decode($json, true)['articles'][0]);
    }

    public function testFormatArticlesArrayUsesDefaultCurrencyWithoutCurrencyData(): void
    {
        $formatted = InvoiceUtils::formatArticlesArray([
            'calculations' => [],
            'articles' => [['sum' => 25]]
        ]);

        self::assertIsArray($formatted);
        self::assertArrayHasKey('display_sum', $formatted['articles'][0]);
    }

    public function testVatHelpers(): void
    {
        $vat = [
            ['text' => '19%', 'sum' => 19.0],
            ['text' => '7%', 'sum' => 3.5]
        ];

        self::assertSame([19.0, 3.5], InvoiceUtils::getVatSumArrayFromVatArray($vat));
        self::assertSame([19, 3.5], InvoiceUtils::getVatSumArrayFromVatArray(json_encode($vat)));
        self::assertSame(22.5, InvoiceUtils::getVatSumFromVatArray($vat));
        self::assertSame(0, InvoiceUtils::getVatSumFromVatArray(null));
        self::assertSame([], InvoiceUtils::getVatTextArrayFromVatArray('{invalid', QUI\ERP\Defaults::getCurrency()));
        self::assertSame([], InvoiceUtils::getVatSumArrayFromVatArray('{invalid'));

        $texts = InvoiceUtils::getVatTextArrayFromVatArray($vat, QUI\ERP\Defaults::getCurrency());
        self::assertCount(2, $texts);
        self::assertStringStartsWith('19%:', $texts[0]);
    }

    #[DataProvider('servicePeriodProvider')]
    public function testNormalizeServicePeriod(mixed $input, array $expected): void
    {
        self::assertSame($expected, json_decode(InvoiceUtils::normalizeServicePeriod($input), true));
    }

    public static function servicePeriodProvider(): iterable
    {
        yield 'iso date' => ['2026-07-17', ['type' => 'date', 'date' => '2026-07-17']];
        yield 'german date' => ['17.07.2026', ['type' => 'date', 'date' => '2026-07-17']];
        yield 'date array' => [
            ['type' => 'date', 'date' => '17.07.2026'],
            ['type' => 'date', 'date' => '2026-07-17']
        ];
        yield 'period string' => [
            '01.07.2026 - 17.07.2026',
            ['type' => 'period', 'start' => '2026-07-01', 'end' => '2026-07-17']
        ];
        yield 'period json' => [
            json_encode(['type' => 'period', 'start' => '2026-07-01', 'end' => '2026-07-17']),
            ['type' => 'period', 'start' => '2026-07-01', 'end' => '2026-07-17']
        ];
    }

    public function testNormalizeEmptyAndIncompleteServicePeriod(): void
    {
        self::assertSame('', InvoiceUtils::normalizeServicePeriod(null));
        self::assertSame('', InvoiceUtils::normalizeServicePeriod(''));
        self::assertSame('', InvoiceUtils::normalizeServicePeriod('   '));
        self::assertSame('', InvoiceUtils::normalizeServicePeriod(['type' => 'period']));
    }

    #[DataProvider('invalidServicePeriodProvider')]
    public function testInvalidServicePeriodThrows(mixed $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        InvoiceUtils::normalizeServicePeriod($input);
    }

    public static function invalidServicePeriodProvider(): iterable
    {
        yield 'invalid date' => ['2026-13-40'];
        yield 'reversed period' => ['17.07.2026 - 01.07.2026'];
        yield 'reversed array period' => [[
            'type' => 'period',
            'start' => '2026-07-17',
            'end' => '2026-07-01'
        ]];
    }

    public function testServicePeriodDataAndDisplayText(): void
    {
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getAttribute')->with('service_period')->willReturn(
            json_encode(['type' => 'date', 'date' => '2026-07-17'])
        );

        self::assertSame(
            ['type' => 'date', 'date' => '2026-07-17'],
            InvoiceUtils::getServicePeriodData($Invoice)
        );
        self::assertIsString(InvoiceUtils::getServicePeriodDisplayText($Invoice, QUI::getLocale()));

        $EmptyInvoice = $this->createMock(InvoiceTemporary::class);
        $EmptyInvoice->method('getAttribute')->with('service_period')->willReturn('');

        self::assertSame([], InvoiceUtils::getServicePeriodData($EmptyInvoice));
        self::assertIsString(InvoiceUtils::getServicePeriodDisplayText($EmptyInvoice, QUI::getLocale()));

        $InvalidInvoice = $this->createMock(Invoice::class);
        $InvalidInvoice->method('getAttribute')->with('service_period')->willReturn('{invalid');
        self::assertSame([], InvoiceUtils::getServicePeriodData($InvalidInvoice));

        $PeriodInvoice = $this->createMock(Invoice::class);
        $PeriodInvoice->method('getAttribute')->with('service_period')->willReturn([
            'type' => 'period',
            'start' => '2026-07-01',
            'end' => '2026-07-17'
        ]);
        self::assertIsString(InvoiceUtils::getServicePeriodDisplayText($PeriodInvoice));
    }

    public function testUnknownInvoiceHasNoTransactions(): void
    {
        self::assertSame([], InvoiceUtils::getTransactionsByInvoice('phpunit-missing-invoice'));
    }

    public function testAddressRequirementThresholdUsesConfiguredValue(): void
    {
        $Config = Settings::getConfig();
        $previousThreshold = $Config->getValue('invoice', 'invoiceAddressRequirementThreshold');

        try {
            $Config->setValue('invoice', 'invoiceAddressRequirementThreshold', '123.45');
            $Config->save();

            self::assertSame(123.45, InvoiceUtils::addressRequirementThreshold());
        } finally {
            if ($previousThreshold === false || $previousThreshold === null) {
                $Config->del('invoice', 'invoiceAddressRequirementThreshold');
            } else {
                $Config->setValue('invoice', 'invoiceAddressRequirementThreshold', $previousThreshold);
            }

            $Config->save();
        }
    }
}
