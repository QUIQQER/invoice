<?php

declare(strict_types=1);

namespace QUI\ERP\Accounting\Invoice\DemoData;

use Doctrine\DBAL\Connection;
use QUI\Locale;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\Contract\DemoDataProviderInterface;

final class InvoiceDemoDataProvider implements DemoDataProviderInterface
{
    public function getIdentifier(): string
    {
        return 'quiqqer.invoice';
    }

    public function getTitle(?Locale $locale = null): string
    {
        $locale ??= \QUI::getLocale();

        return (string)$locale->get('quiqqer/invoice', 'demo_data.provider.title');
    }

    public function getDemoDataCreator(Connection $connection): DemoDataCreatorInterface
    {
        return new InvoiceDemoDataCreator();
    }
}
