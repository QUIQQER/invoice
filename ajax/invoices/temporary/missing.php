<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_temporary_list
 */

use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

/**
 * Returns missing attributes
 *
 * @param string $invoiceId - ID of the Invoice
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_temporary_missing',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();
        $Invoice  = $Invoices->getTemporaryInvoice($invoiceId);

        $missed = $Invoice->getMissingAttributes();
        $result = array();

        foreach ($missed as $missing) {
            $result[$missing] = InvoiceUtils::getMissingAttributeMessage($missing);
        }

        return $result;
    },
    array('invoiceId'),
    'Permission::checkAdminUser'
);
