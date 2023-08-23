<?php

/**
 * Update a processing status
 */

use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler;

QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_processingStatus_update',
    function ($id, $color, $title, $options) {
        if (!empty($options)) {
            $options = \json_decode($options, true);
        } else {
            $options = [];
        }

        Handler::getInstance()->updateProcessingStatus(
            $id,
            $color,
            \json_decode($title, true),
            $options
        );
    },
    ['id', 'color', 'title', 'options'],
    'Permission::checkAdminUser'
);
