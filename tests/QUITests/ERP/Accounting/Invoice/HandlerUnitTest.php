<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\Handler;
use ReflectionMethod;

class HandlerUnitTest extends TestCase
{
    public static function orderProvider(): array
    {
        return [
            'field only' => ['date', true],
            'ascending' => ['date ASC', true],
            'descending lowercase' => ['date desc', true],
            'surrounding spaces' => ['  date DESC  ', true],
            'unknown field' => ['unknown DESC', false],
            'unknown direction' => ['date RANDOM', false],
            'multiple inner spaces' => ['date  DESC', false],
            'additional part' => ['date DESC extra', false],
            'multiple fields' => ['date DESC, id', false],
            'tab separator' => ["date\tDESC", false],
            'empty value' => ['', false],
            'non-string value' => [123, false]
        ];
    }

    #[DataProvider('orderProvider')]
    public function testTemporaryInvoiceOrderValidation(mixed $order, bool $expected): void
    {
        $Method = new ReflectionMethod(Handler::class, 'canBeUseAsOrderField');

        self::assertSame($expected, $Method->invoke(Handler::getInstance(), $order));
    }
}
