<?php

/**
 * Create a new  processing status
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory;

QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_create',
    function ($id, $color, $title, $options) {
        if (!empty($options)) {
            $options = json_decode($options, true);
        } else {
            $options = [];
        }

        Factory::getInstance()->createProcessingStatus(
            $id,
            $color,
            json_decode($title, true),
            $options
        );
    },
    ['id', 'color', 'title', 'options'],
    'Permission::checkAdminUser'
);
