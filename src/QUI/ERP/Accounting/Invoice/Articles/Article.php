<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Articles\Article
 */
namespace QUI\ERP\Accounting\Invoice\Articles;

use QUI;

/**
 * Article
 * An temporary invoice article
 *
 * - Freier Artikel
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Article implements ArticleInterface
{
    /**
     * @var array
     */
    protected $attributes = array();

    /**
     * Article constructor.
     *
     * @param array $attributes - (title, description, unitPrice, quantity)
     */
    public function __construct($attributes = array())
    {
        if (isset($attributes['title'])) {
            $this->attributes['title'] = $attributes['title'];
        }

        if (isset($attributes['description'])) {
            $this->attributes['description'] = $attributes['description'];
        }

        if (isset($attributes['unitPrice'])) {
            $this->attributes['unitPrice'] = $attributes['unitPrice'];
        }

        if (isset($attributes['quantity'])) {
            $this->attributes['quantity'] = $attributes['quantity'];
        }
    }

    /**
     * Returns the article title
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if (isset($this->attributes['title'])) {
            return $this->attributes['title'];
        }

        return '';
    }

    /**
     * Returns the article description
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getDescription($Locale = null)
    {
        if (isset($this->attributes['description'])) {
            return $this->attributes['description'];
        }

        return '';
    }

    /**
     * Returns the article unit price
     *
     * @return int|float
     */
    public function getUnitPrice()
    {
        if (isset($this->attributes['unitPrice'])) {
            return $this->attributes['unitPrice'];
        }

        return 0;
    }

    /**
     * Returns the article total sum
     *
     * @return int|float
     */
    public function getSum()
    {
        return 0;
    }

    /**
     * Returns the article quantity
     *
     * @return int
     */
    public function getQuantity()
    {
        if (isset($this->attributes['quantity'])) {
            return $this->attributes['quantity'];
        }

        return 1;
    }

    /**
     * Return the article as an array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'title'       => $this->getTitle(),
            'description' => $this->getDescription(),
            'unitPrice'   => $this->getUnitPrice(),
            'quantity'    => $this->getQuantity(),
            'sum'         => $this->getSum(),
            'control'     => 'package/quiqqer/invoice/bin/backend/controls/articles/Article'
        );
    }
}
