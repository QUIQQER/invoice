<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ErpProvider
 */
namespace QUI\ERP\Accounting\Invoice;

use QUI\ERP\Api\AbstractFactory;

/**
 * Class ErpProvider
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class ErpProvider extends AbstractFactory
{
    /**
     * @return array
     */
    public static function getMenuItems()
    {
        $menu = array();

        $menu[] = array(
            'icon'   => 'fa fa-money',
            'text'   => array('quiqqer/invoice', 'erp.panel.bills.text'),
            'events' => array(
                'onClick' => ''
            )
        );

        $menu[] = array(
            'icon'   => 'fa fa-money',
            'text'   => array('quiqqer/invoice', 'erp.panel.bills.create.text'),
            'events' => array(
                'onClick' => ''
            )
        );

        return $menu;
    }
}
