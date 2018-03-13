<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles\Article
 */

namespace QUI\ERP\Accounting\Invoice\Articles;

use QUI;

/**
 * Article
 * An temporary invoice article
 *
 * - Freier Artikel
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Article extends QUI\ERP\Accounting\Article
{
    /**
     * @inheritdoc
     * @return array
     */
    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'control' => 'package/quiqqer/invoice/bin/backend/controls/articles/Article'
        ]);
    }
}
