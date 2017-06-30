<?php

/**
 * This file contains package_quiqqer_invoice_ajax_processingStatus_create
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory;

/**
 * Create a new  processing status
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_create',
    function ($id, $color, $title) {
        Factory::getInstance()->createProcessingStatus(
            $id,
            $color,
            json_decode($title, true)
        );
    },
    array('id', 'color', 'title'),
    'Permission::checkAdminUser'
);
