<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\NumberRanges\TemporaryInvoice
 */

namespace QUI\ERP\Accounting\Invoice\NumberRanges;

use QUI;
use QUI\Database\Exception;
use QUI\ERP\Api\NumberRangeInterface;
use QUI\Utils\Doctrine;

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

        return (int)QUI::getDataBaseConnection()->fetchOne(
            'SELECT AUTO_INCREMENT FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
            ['table' => $Handler->temporaryInvoiceTable()]
        );
    }

    /**
     * @param int $range
     */
    public function setRange(int $range): void
    {
        $Handler = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $tableName = $Handler->temporaryInvoiceTable();

        QUI::getDataBaseConnection()->executeStatement(
            'ALTER TABLE ' . Doctrine::quoteIdentifier($tableName) . ' AUTO_INCREMENT = ' . (int)$range
        );
    }
}
