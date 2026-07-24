<?php

/**
 * This file is part of the QUIQQER project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\DemoData;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\DemoData\InvoiceDemoDataProvider;
use QUI\Locale;

class InvoiceDemoDataProviderTest extends TestCase
{
    public function testReturnsIdentifierAndLocalizedTitle(): void
    {
        /** @var Locale&MockObject $Locale */
        $Locale = $this->createMock(Locale::class);
        $Locale->expects($this->once())
            ->method('get')
            ->with('quiqqer/invoice', 'package.title')
            ->willReturn('Invoices');

        $Provider = new InvoiceDemoDataProvider();

        self::assertSame('quiqqer.invoice', $Provider->getIdentifier());
        self::assertSame('Invoices', $Provider->getTitle($Locale));
    }
}
