<?php

/**
 * This file contains package_quiqqer_invoice_ajax_processingStatus_delete
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler;

/**
 * Delete a processing status
 *
 * @param int $id
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_delete',
    function ($id) {
        Handler::getInstance()->deleteProcessingStatus($id);
    },
    ['id'],
    'Permission::checkAdminUser'
);
