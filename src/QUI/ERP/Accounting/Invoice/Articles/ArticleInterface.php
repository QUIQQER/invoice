<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles
 */
namespace QUI\ERP\Accounting\Invoice\Articles;

use QUI;

/**
 * Article
 * An temporary invoice article
 *
 * @package QUI\ERP\Accounting\Invoice
 */
interface ArticleInterface
{
    /**
     * Article constructor.
     *
     * @param array $attributes - article attributes
     * @throws \QUI\Exception
     */
    public function __construct($attributes = array());

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null);

    /**
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription($Locale = null);

    /**
     * @return integer|float
     */
    public function getUnitPrice();

    /**
     * @return integer|float
     */
    public function getSum();

    /**
     * @return integer|float
     */
    public function getQuantity();

    /**
     * @return array
     */
    public function toArray();
}
