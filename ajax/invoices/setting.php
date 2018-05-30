<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_setting
 */

/**
 * Return a specific setting
 *
 * @param string $section - setting section
 * @param string $key - setting key
 *
 * @return string|integer
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_setting',
    function ($section, $key) {
        return QUI\ERP\Accounting\Invoice\Settings::getInstance()->get($section, $key);
    },
    ['section', 'key'],
    'Permission::checkAdminUser'
);
