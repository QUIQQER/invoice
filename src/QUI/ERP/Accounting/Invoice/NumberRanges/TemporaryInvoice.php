<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\NumberRanges\TemporaryInvoice
 */

namespace QUI\ERP\Accounting\Invoice\NumberRanges;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

use function is_numeric;

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
     */
    public function getRange(): int
    {
        $Table = QUI::getDataBase()->table();
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        return $Table->getAutoIncrementIndex(
            $Handler->temporaryInvoiceTable()
        );
    }

    /**
     * @param int $range
     */
    public function setRange(int $range): void
    {
        if (!is_numeric($range)) {
            return;
        }

        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $tableName = $Handler->temporaryInvoiceTable();
        $PDO = QUI::getDataBase()->getPDO();

        $Statement = $PDO->prepare(
            "ALTER TABLE $tableName AUTO_INCREMENT = " . (int)$range
        );

        $Statement->execute();
    }
}
