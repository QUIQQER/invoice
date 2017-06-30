<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_settings_templates
 */

use QUI\ERP\Accounting\Invoice\Settings;

/**
 * Returns the invoice templates
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_settings_templates',
    function () {
        return Settings::getInstance()->getAvailableTemplates();
    },
    false,
    'Permission::checkAdminUser'
);
