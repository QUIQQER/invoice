<?php

/**
 * This file is part of the QUIQQER project.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace QUITests\ERP\Accounting\Invoice\DemoData;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\DemoData\InvoiceDemoDataProvider;

class InvoiceDemoDataProviderTest extends TestCase
{
    public function testReturnsIdentifier(): void
    {
        $Provider = new InvoiceDemoDataProvider();

        self::assertSame('quiqqer.invoice', $Provider->getIdentifier());
    }
}
