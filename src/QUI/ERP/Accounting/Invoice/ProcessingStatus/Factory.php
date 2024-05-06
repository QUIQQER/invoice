<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory
 */

namespace QUI\ERP\Accounting\Invoice\ProcessingStatus;

use QUI;

use function array_keys;
use function count;
use function json_encode;
use function max;

/**
 * Class Factory
 * - For processing status creation
 *
 * @package QUI\ERP\Accounting\Invoice\ProcessingStatus
 */
class Factory extends QUI\Utils\Singleton
{
    /**
     * Create a new processing status
     *
     * @param integer|string $id - processing ID
     * @param string $color - color of the status
     * @param array $title - title
     * @param array $options (optional) - Status options
     * @throws Exception|QUI\Exception
     * @todo permissions
     */
    public function createProcessingStatus(
        int|string $id,
        string $color,
        array $title,
        array $options = []
    ): void {
        $list = Handler::getInstance()->getList();
        $id = (int)$id;
        $data = [];

        if (isset($list[$id])) {
            throw new Exception([
                'quiqqer/invoice',
                'exception.processStatus.exists'
            ]);
        }

        // config
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config = $Package->getConfig();

        $Config->setValue(
            'processing_status',
            $id,
            json_encode([
                'color' => $color,
                'options' => $options
            ])
        );

        $Config->save();

        // translations
        $languages = QUI::availableLanguages();

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
            }
        }

        $data['package'] = 'quiqqer/invoice';
        $data['datatype'] = 'php,js';
        $data['html'] = 1;

        QUI\Translator::addUserVar(
            'quiqqer/invoice',
            'processing.status.' . $id,
            $data
        );

        QUI\Translator::publish('quiqqer/invoice');
    }

    /**
     * Return a next ID to create a new Processing Status
     *
     * @return int
     */
    public function getNextId(): int
    {
        $list = Handler::getInstance()->getList();

        if (!count($list)) {
            return 1;
        }

        $max = max(array_keys($list));

        return $max + 1;
    }
}
