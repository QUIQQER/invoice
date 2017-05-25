<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Search\InvoiceSearch
 */

namespace QUI\ERP\Accounting\Invoice\Search;

use QUI\Utils\Singleton;

/**
 * Class Search
 * - Searches invoices
 *
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class InvoiceSearch extends Singleton
{
    /**
     * @var array
     */
    protected $filter = array();

    /**
     * @var array
     */
    protected $allowedFilters = array(
        'from',
        'to'
    );

    /**
     * @param string $filter
     * @param string $value
     */
    public function setFilter($filter, $value)
    {
        $this->filter[$filter] = $value;
    }


    public function limit($from, $to)
    {

    }


    public function search()
    {

    }


    public function searchForGrid()
    {

    }
}
