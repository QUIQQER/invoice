<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles\Text
 */

namespace QUI\ERP\Accounting\Invoice\Articles;

use QUI;

/**
 * Article Text
 *
 * - Ein Artikel welcher nur Text beinhaltet
 * - Kann als Informationsposition auf einer Rechnung verwendet werden
 * - Besitzt keine Werte
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Text extends QUI\ERP\Accounting\Article
{
    /**
     * @inheritdoc
     * @return int
     */
    public function getUnitPrice()
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @return int
     */
    public function getSum()
    {
        return 0;
    }

    /**
     * @inheritdoc
     * @return bool
     */
    public function getQuantity()
    {
        return false;
    }

    /**
     * @inheritdoc
     * @return array
     */
    public function toArray()
    {
        return array_merge(parent::toArray(), array(
            'control' => 'package/quiqqer/invoice/bin/backend/controls/articles/Text'
        ));
    }
}
