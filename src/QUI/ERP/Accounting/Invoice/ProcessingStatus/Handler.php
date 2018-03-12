<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler
 */

namespace QUI\ERP\Accounting\Invoice\ProcessingStatus;

use QUI;

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
     * @var array
     */
    protected $list = null;

    /**
     * Return all processing status entries from the config
     *
     * @return array
     */
    public function getList()
    {
        if ($this->list !== null) {
            return $this->list;
        }

        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();
        $result  = $Config->getSection('processing_status');

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
    public function getProcessingStatusList()
    {
        $list   = $this->getList();
        $result = [];

        foreach ($list as $entry => $color) {
            try {
                $result[] = $this->getProcessingStatus($entry);
            } catch (Exception $Exception) {
            }
        }

        return $result;
    }

    /**
     * Return a processing status
     *
     * @param $id
     * @return Status
     */
    public function getProcessingStatus($id)
    {
        return new Status($id);
    }

    /**
     * Delete / Remove a processing status
     *
     * @param string|int $id
     *
     * @todo permissions
     */
    public function deleteProcessingStatus($id)
    {
        $Status = $this->getProcessingStatus($id);

        // remove translation
        QUI\Translator::delete(
            'quiqqer/invoice',
            'processing.status.'.$Status->getId()
        );

        QUI\Translator::publish('quiqqer/invoice');

        // update config
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();

        $Config->del('processing_status', $Status->getId());
        $Config->save();
    }

    /**
     * Update a processing status
     *
     * @param int|string $id
     * @param int|string $color
     * @param array $title
     *
     * @todo permissions
     */
    public function updateProcessingStatus($id, $color, array $title)
    {
        $Status = $this->getProcessingStatus($id);

        // update translation
        $languages = QUI::availableLanguages();

        $data = [
            'package'  => 'quiqqer/invoice',
            'datatype' => 'php,js',
            'html'     => 1
        ];

        foreach ($languages as $language) {
            if (isset($title[$language])) {
                $data[$language]         = $title[$language];
                $data[$language.'_edit'] = $title[$language];
            }
        }

        QUI\Translator::edit(
            'quiqqer/invoice',
            'processing.status.'.$Status->getId(),
            'quiqqer/invoice',
            $data
        );

        QUI\Translator::publish('quiqqer/invoice');


        // update config
        $Package = QUI::getPackage('quiqqer/invoice');
        $Config  = $Package->getConfig();

        $Config->setValue('processing_status', $Status->getId(), $color);
        $Config->save();
    }
}
