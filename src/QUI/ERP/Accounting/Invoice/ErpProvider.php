<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ErpProvider
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI\Controls\Sitemap\Map;
use QUI\Controls\Sitemap\Item;

use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @param \QUI\Controls\Sitemap\Map $Map
     */
    public static function addMenuItems(Map $Map)
    {
        $Accounting = $Map->getChildrenByName('accounting');

        if ($Accounting === null) {
            $Accounting = new Item([
                'icon'     => 'fa fa-book',
                'name'     => 'accounting',
                'text'     => ['quiqqer/order', 'erp.panel.accounting.text'],
                'opened'   => true,
                'priority' => 1
            ]);

            $Map->appendChild($Accounting);
        }

        $Invoice = new Item([
            'icon'     => 'fa fa-file-text-o',
            'name'     => 'invoice',
            'text'     => ['quiqqer/invoice', 'erp.panel.invoice.text'],
            'opened'   => true,
            'priority' => 2
        ]);

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-plus',
                'name'    => 'invoice-create',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.create.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/utils/ErpMenuInvoiceCreate'
            ])
        );

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-file-text-o',
                'name'    => 'invoice-drafts',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.drafts.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices'
            ])
        );

        $Invoice->appendChild(
            new Item([
                'icon'    => 'fa fa-file-text-o',
                'name'    => 'invoice-journal',
                'text'    => ['quiqqer/invoice', 'erp.panel.invoice.journal.text'],
                'require' => 'package/quiqqer/invoice/bin/backend/controls/panels/Journal'
            ])
        );

        $Accounting->appendChild($Invoice);
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return [
            new NumberRanges\Invoice(),
            new NumberRanges\TemporaryInvoice()
        ];
    }
}
