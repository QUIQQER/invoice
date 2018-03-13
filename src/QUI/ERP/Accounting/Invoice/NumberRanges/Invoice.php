<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\NumberRanges\Invoice
 */

namespace QUI\ERP\Accounting\Invoice\NumberRanges;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

/**
 * Class Invoice
 *
 * @package QUI\ERP\Accounting\Invoice\NumberRanges
 */
class Invoice implements NumberRangeInterface
{
    /**
     * @param null|QUI\Locale $Locale
     *
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'invoice.numberrange.title');
    }

    /**
     * Return the current start range value
     *
     * @return int
     */
    public function getRange()
    {
        $Table   = QUI::getDataBase()->table();
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        return $Table->getAutoIncrementIndex(
            $Handler->invoiceTable()
        );
    }

    /**
     * @param int $range
     */
    public function setRange($range)
    {
        if (!is_numeric($range)) {
            return;
        }

        $PDO       = QUI::getDataBase()->getPDO();
        $Handler   = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $tableName = $Handler->invoiceTable();

        $Statement = $PDO->prepare(
            "ALTER TABLE {$tableName} AUTO_INCREMENT = ".(int)$range
        );

        $Statement->execute();
    }
}
