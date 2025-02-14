<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler
 */

namespace QUI\ERP\Accounting\Invoice\ProcessingStatus;

use QUI;

use function is_array;
use function json_encode;

/**
 * Class Handler
 * - Processing status management
 * - Returns processing status objects
 * - Returns processing status lists
 *
 * @package QUI\ERP\Accounting\Invoice\ProcessingStatus
 */
class Handler extends QUI\Utils\Singleton
{
    /**
     * Status options
     */
    const STATUS_OPTION_PREVENT_INVOICE_POSTING = 'preventInvoicePosting';

    /**
     * @var ?array
     */
    protected ?array $list = null;

    /**
     * Return all processing status entries from the config
     *
     * @return array
     */
    public function getList(): array
    {
        if ($this->list !== null) {
            return $this->list;
        }

        try {
            $Package = QUI::getPackage('quiqqer/invoice');
            $Config = $Package->getConfig();
        } catch (QUI\Exception) {
            return [];
        }

        $result = $Config->getSection('processing_status');

        if (!$result || !is_array($result)) {
            $this->list = [];

            return $this->list;
        }

        $this->list = $result;

        return $result;
    }

    /**
     * Return the complete processing status objects
     *
     * @return array
     */
    public function getProcessingStatusList(): array
    {
        $list = $this->getList();
        $result = [];

        foreach ($list as $statusId => $v) {
            try {
                $result[] = $this->getProcessingStatus($statusId);
            } catch (Exception) {
            }
        }

        return $result;
    }

    /**
     * Return a processing status
     *
     * @param $id
     * @return Status
     *
     * @throws Exception
     */
    public function getProcessingStatus($id): Status
    {
        return new Status($id);
    }

    /**
     * Delete / Remove a processing status
     *
     * @param int|string $id
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function deleteProcessingStatus(int|string $id): void
    {
        $Status = $this->getProcessingStatus($id);

        // remove translation
        QUI\Translator::delete(
            'quiqqer/invoice',
            'processing.status.' . $Status->getId()
        );

        QUI\Translator::publish('quiqqer/invoice');

        // update config
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config = $Package->getConfig();

        $Config->del('processing_status', (string)$Status->getId());
        $Config->save();
    }

    /**
     * Update a processing status
     *
     * @param int|string $id
     * @param int|string $color
     * @param array $title
     * @param array $options (optional)
     *
     * @throws Exception
     * @throws QUI\Exception
     *
     * @todo permissions
     */
    public function updateProcessingStatus(
        int|string $id,
        int|string $color,
        array $title,
        array $options = []
    ): void {
        $Status = $this->getProcessingStatus($id);

        // update translation
        $languages = QUI::availableLanguages();

        $data = [
            'package' => 'quiqqer/invoice',
            'datatype' => 'php,js',
            'html' => 1
        ];

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language] = $title[$language];
                $data[$language . '_edit'] = $title[$language];
            }
        }

        QUI\Translator::edit(
            'quiqqer/invoice',
            'processing.status.' . $Status->getId(),
            'quiqqer/invoice',
            $data
        );

        QUI\Translator::publish('quiqqer/invoice');

        // update config
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config = $Package->getConfig();

        $Config->setValue(
            'processing_status',
            (string)$Status->getId(),
            json_encode([
                'color' => $color,
                'options' => $options
            ])
        );

        $Config->save();
    }
}
