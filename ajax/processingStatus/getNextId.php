<?php

/**
 * This file contains package_quiqqer_invoice_ajax_processingStatus_getNextId
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory;

/**
 * Return next available ID
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_getNextId',
    function () {
        return Factory::getInstance()->getNextId();
    },
    false,
    'Permission::checkAdminUser'
);
