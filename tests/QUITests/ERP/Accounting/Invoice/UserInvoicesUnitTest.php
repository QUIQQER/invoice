<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\FrontendUsers\UserInvoices;
use QUI\Interfaces\Projects\Site;

class UserInvoicesUnitTest extends TestCase
{
    public function testControlRendersForExplicitUserAndExposesSite(): void
    {
        $Control = new UserInvoices([
            'User' => QUI::getUsers()->getSystemUser(),
            'limit' => 0,
            'page' => 1
        ]);

        self::assertIsString($Control->getBody());

        $Site = $this->createMock(Site::class);
        $Control->setAttribute('Site', $Site);

        self::assertSame($Site, $Control->getSite());

        $Control->onSave();
        $Control->validate();

        self::assertSame(5, $Control->getAttribute('limit'));
    }
}
