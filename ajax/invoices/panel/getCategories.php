<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_panel_getCategories
 */

QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_panel_getCategories',
    function () {
        try {
            return QUI\ERP\Accounting\Invoice\Utils\Panel::getPanelCategories();
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
            return [];
        }
    },
    false,
    'Permission::checkAdminUser'
);
