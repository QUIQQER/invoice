<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\ProcessingStatus\Status
 */

namespace QUI\ERP\Accounting\Invoice\ProcessingStatus;

use QUI;

/**
 * Class Exception
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Status
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $color;

    /**
     * Status constructor.
     *
     * @param int|\string $id - Processing status id
     * @throws Exception
     */
    public function __construct($id)
    {
        $list = Handler::getInstance()->getList();

        if (!isset($list[$id])) {
            throw new Exception(array(
                'quiqqer/invoice',
                'exception.processingStatus.not.found'
            ));
        }

        $this->id    = (int)$id;
        $this->color = $list[$id];
    }

    //region Getter

    /**
     * Return the status id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Return the title
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (!($Locale instanceof QUI\Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'processing.status.' . $this->id);
    }

    /**
     * Return the status color
     *
     * @return string
     */
    public function getColor()
    {
        return $this->color;
    }

    //endregion

    /**
     * Status as array
     *
     * @param null|QUI\Locale $Locale - optional. if no locale, all translations would be returned
     * @return array
     */
    public function toArray($Locale = null)
    {
        $title = $this->getTitle($Locale);

        if ($Locale === null) {
            $title     = array();
            $Locale    = QUI::getLocale();
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                $title[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/invoice',
                    'processing.status.' . $this->getId()
                );
            }
        }

        return array(
            'id'    => $this->getId(),
            'title' => $title,
            'color' => $this->getColor()
        );
    }
}
