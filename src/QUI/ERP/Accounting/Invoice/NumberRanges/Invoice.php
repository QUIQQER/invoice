<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\NumberRanges\Invoice
 */

namespace QUI\ERP\Accounting\Invoice\NumberRanges;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

use function is_numeric;

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
    public function getTitle($Locale = null): string
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
    public function getRange(): int
    {
        $Config = QUI::getPackage('quiqqer/invoice')->getConfig();
        $invoiceId = $Config->getValue('invoice', 'invoiceCurrentIdIndex');

        if (empty($invoiceId)) {
            return 1;
        }

        return (int)$invoiceId + 1;
    }

    /**
     * @param int $range
     */
    public function setRange($range): void
    {
        if (!is_numeric($range)) {
            return;
        }

        $Config = QUI::getPackage('quiqqer/invoice')->getConfig();
        $Config->set('invoice', 'invoiceCurrentIdIndex', $range);
        $Config->save();
    }
}
