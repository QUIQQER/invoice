<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles\Text
 */

namespace QUI\ERP\Accounting\Invoice\Articles;

use QUI;
use QUI\ERP\Money\Price;

/**
 * Article Text
 *
 * - An article containing only text
 * - Can be used as an information item on an invoice
 * - Does not have any values
 *
 * - Ein Artikel welcher nur Text beinhaltet
 * - Kann als Informationsposition auf einer Rechnung verwendet werden
 * - Besitzt keine Werte
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Text extends QUI\ERP\Accounting\Article
{
    protected $displayPrice = false;

    /**
     * @inheritdoc
     * @return QUI\ERP\Money\Price
     */
    public function getUnitPrice()
    {
        return new Price(0, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * @inheritdoc
     * @return QUI\ERP\Money\Price
     */
    public function getSum()
    {
        return new Price(0, QUI\ERP\Defaults::getCurrency());
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function getQuantity()
    {
        return 1;
    }

    /**
     * @return bool
     */
    public function displayPrice()
    {
        return false;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function toArray()
    {
        return \array_merge(parent::toArray(), [
            'class'        => \get_class($this),
            'control'      => 'package/quiqqer/invoice/bin/backend/controls/articles/Text',
            'displayPrice' => $this->displayPrice()
        ]);
    }
}
