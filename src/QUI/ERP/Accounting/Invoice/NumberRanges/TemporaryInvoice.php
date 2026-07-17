<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\NumberRanges\TemporaryInvoice
 */

namespace QUI\ERP\Accounting\Invoice\NumberRanges;

use QUI;
use QUI\Database\Exception;
use QUI\ERP\Api\NumberRangeInterface;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\Utils\Doctrine;

use function is_numeric;
use function max;

/**
 * Class TemporaryInvoice
 *
 * @package QUI\ERP\Accounting\Invoice\NumberRanges
 */
class TemporaryInvoice implements NumberRangeInterface
{
    /**
     * @param null|QUI\Locale $Locale
     *
     * @return string
     */
    public function getTitle(?QUI\Locale $Locale = null): string
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'invoice.temporary.numberrange.title');
    }

    /**
     * Return the current start range value
     *
     * @return int
     * @throws Exception
     */
    public function getRange(): int
    {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Config = Settings::getConfig();
        $currentId = $Config->getValue('invoice', 'temporaryInvoiceCurrentIdIndex');

        if (is_numeric($currentId)) {
            return (int)$currentId + 1;
        }

        return $this->getNextAvailableId($Handler->temporaryInvoiceTable());
    }

    /**
     * @param int $range
     */
    public function setRange(int $range): void
    {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $tableName = $Handler->temporaryInvoiceTable();
        $nextAvailableId = $this->getNextAvailableId($tableName);

        $Config = Settings::getConfig();
        $Config->setValue(
            'invoice',
            'temporaryInvoiceCurrentIdIndex',
            max($range, $this->getRange(), $nextAvailableId) - 1
        );
        $Config->save();
    }

    private function getNextAvailableId(string $tableName): int
    {
        $nextAvailableId = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('COALESCE(MAX(' . Doctrine::quoteIdentifier('id') . '), 0) + 1')
            ->from(Doctrine::quoteIdentifier($tableName))
            ->executeQuery()
            ->fetchOne();

        return (int)$nextAvailableId;
    }
}
