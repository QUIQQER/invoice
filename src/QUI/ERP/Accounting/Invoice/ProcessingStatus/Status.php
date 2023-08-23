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
     * Default options.
     *
     * @var array
     */
    protected array $options = [
        Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING => false
    ];

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
            throw new Exception([
                'quiqqer/invoice',
                'exception.processingStatus.not.found'
            ]);
        }

        $this->id = (int)$id;
        $data = $list[$id];

        // Fallback for old data structure
        if (\mb_strpos($data, '#') === 0) {
            $this->color = $data;
        } else {
            $data = \json_decode($data, true);

            $this->color = $data['color'];

            foreach ($data['options'] as $k => $v) {
                $this->setOption($k, $v);
            }
        }
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

    // region Options

    /**
     * Set status option
     *
     * @param string $key - See $this->options for available options
     * @param $value
     */
    public function setOption(string $key, $value)
    {
        if (\array_key_exists($key, $this->options)) {
            $this->options[$key] = $value;
        }
    }

    /**
     * Get status option
     *
     * @param string $key
     * @return mixed|null - Option value or NULL if option does not exist
     */
    public function getOption(string $key)
    {
        if (\array_key_exists($key, $this->options)) {
            return $this->options[$key];
        }

        return null;
    }

    /**
     * Get all status options
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    // endregion

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
            $title = [];
            $Locale = QUI::getLocale();
            $languages = QUI::availableLanguages();

            foreach ($languages as $language) {
                $title[$language] = $Locale->getByLang(
                    $language,
                    'quiqqer/invoice',
                    'processing.status.' . $this->getId()
                );
            }
        }

        return [
            'id' => $this->getId(),
            'title' => $title,
            'color' => $this->getColor(),
            'options' => $this->getOptions()
        ];
    }
}
