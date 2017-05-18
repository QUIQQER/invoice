<?php

/**
 * This file contains package_quiqqer_invoice_ajax_processingStatus_update
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler;

/**
 * Update a processing status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_update',
    function ($id, $color, $title) {
        Handler::getInstance()->updateProcessingStatus(
            $id,
            $color,
            json_decode($title, true)
        );
    },
    array('id', 'color', 'title'),
    'Permission::checkAdminUser'
);
