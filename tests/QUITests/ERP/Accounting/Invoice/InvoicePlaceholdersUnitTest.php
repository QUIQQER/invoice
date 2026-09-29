<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\User;
use ReflectionMethod;

class InvoicePlaceholdersUnitTest extends TestCase
{
    /**
     * @param array<string, mixed> $attributes
     */
    #[DataProvider('customerNames')]
    public function testCustomerNameFallbacks(array $attributes, string $name, string $companyOrName): void
    {
        $Customer = new User($attributes + ['uuid' => 'invoice-placeholder-test', 'lang' => 'de']);
        $variables = OutputProviderInvoice::getCustomerVariables($Customer);

        self::assertSame($name, $variables['name']);
        self::assertSame($name, $variables['user']);
        self::assertSame($companyOrName, $variables['companyOrName']);

        $Draft = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrefixedNumber', 'getAttribute'])
            ->getMock();
        $Draft->method('getPrefixedNumber')->willReturn('DRAFT-133');
        $Draft->method('getAttribute')->willReturnCallback(
            static fn ($attribute) => $attribute === 'date' ? '2026-09-29' : ''
        );
        $Method = new ReflectionMethod(OutputProviderInvoice::class, 'getInvoiceLocaleVar');
        $mailVariables = $Method->invoke(null, $Draft, $Customer);

        self::assertSame($name, $mailVariables['contactPersonOrName']);
        self::assertSame($name, $mailVariables['contactPerson']);
        self::assertSame($name, $mailVariables['name']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function customerNames(): iterable
    {
        yield 'customer email only' => [
            ['email' => 'customer@example.invalid'],
            'customer@example.invalid',
            'customer@example.invalid'
        ];
        yield 'address email only' => [
            ['address' => ['mail' => ['address@example.invalid']]],
            'address@example.invalid',
            'address@example.invalid'
        ];
        yield 'contact email only' => [
            ['contactEmail' => 'contact@example.invalid'],
            'contact@example.invalid',
            'contact@example.invalid'
        ];
        yield 'customer name before email' => [
            ['firstname' => 'Erika', 'lastname' => 'Muster', 'email' => 'customer@example.invalid'],
            'Erika Muster',
            'Erika Muster'
        ];
        yield 'address contact before customer name' => [
            ['firstname' => 'Erika', 'lastname' => 'Muster', 'address' => ['contactPerson' => 'Erika Kontakt']],
            'Erika Kontakt',
            'Erika Kontakt'
        ];
        yield 'company before name' => [
            ['firstname' => 'Erika', 'lastname' => 'Muster', 'address' => ['company' => 'Example GmbH']],
            'Erika Muster',
            'Example GmbH'
        ];
        yield 'whitespace does not block email fallback' => [
            [
                'firstname' => ' ',
                'lastname' => ' ',
                'email' => ' customer@example.invalid ',
                'address' => ['company' => ' ', 'contactPerson' => ' ']
            ],
            'customer@example.invalid',
            'customer@example.invalid'
        ];
        yield 'no name or email' => [[], '', ''];
    }

    public function testExplicitInvoiceContactTakesPrecedence(): void
    {
        $Customer = new User([
            'uuid' => 'invoice-placeholder-test',
            'firstname' => 'Erika',
            'lastname' => 'Muster',
            'lang' => 'de',
            'email' => 'customer@example.invalid'
        ]);
        $Draft = $this->getMockBuilder(InvoiceTemporary::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getPrefixedNumber', 'getAttribute'])
            ->getMock();
        $Draft->method('getPrefixedNumber')->willReturn('DRAFT-133');
        $Draft->method('getAttribute')->willReturnMap([
            ['date', '2026-09-29'],
            ['hash', 'invoice-placeholder-test'],
            ['contact_person', 'Manueller Ansprechpartner']
        ]);

        $Method = new ReflectionMethod(OutputProviderInvoice::class, 'getInvoiceLocaleVar');
        $variables = $Method->invoke(null, $Draft, $Customer);

        self::assertSame('Manueller Ansprechpartner', $variables['contactPerson']);
        self::assertSame('Manueller Ansprechpartner', $variables['contactPersonOrName']);
        self::assertSame('Erika Muster', $variables['name']);
    }
}
