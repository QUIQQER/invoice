<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ErpProvider
 */
namespace QUI\ERP\Accounting\Invoice;

use QUI\ERP\Api\AbstractErpProvider;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class ErpProvider extends AbstractErpProvider
{
    /**
     * @return array
     */
    public static function getMenuItems()
    {
        $menu = array();

        $menu[] = array(
            'icon'  => 'fa fa-money',
            'text'  => array('quiqqer/invoice', 'erp.panel.invoice.text'),
            'panel' => 'package/quiqqer/invoice/bin/backend/controls/panels/Journal'
        );

        $menu[] = array(
            'icon'  => 'fa fa-money',
            'text'  => array('quiqqer/invoice', 'erp.panel.invoice.create.text'),
            'panel' => 'package/quiqqer/invoice/bin/backend/controls/panels/TemporaryInvoices'
        );

        return $menu;
    }

    /**
     * @return array
     */
    public static function getNumberRanges()
    {
        return array(
            new NumberRanges\Invoice(),
            new NumberRanges\TemporaryInvoice()
        );
    }
}
