<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Search\InvoiceSearch
 */

namespace QUI\ERP\Accounting\Invoice\Search;

use QUI;
use QUI\Utils\Singleton;

use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\Settings;

use QUI\ERP\Currency\Handler as Currencies;
use QUI\ERP\Accounting\Payments\Handler as Payments;


/**
 * Class Search
 * - Searches invoices
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
    protected $limit = array(0, 20);

    /**
     * @var string
     */
    protected $order = 'id DESC';

    /**
     * @var array
     */
    protected $allowedFilters = array(
        'from',
        'to',
        'paid_status'
    );

    /**
     * @var array
     */
    protected $cache = array();

    /**
     * Set a filter
     *
     * @param string $filter
     * @param string|array $value
     */
    public function setFilter($filter, $value)
    {
        $keys = array_flip($this->allowedFilters);

        if (!isset($keys[$filter])) {
            return;
        }

        if (!is_array($value)) {
            $value = array($value);
        }

        foreach ($value as $val) {
            if ($filter === 'paid_status' && $val === '') {
                continue;
            }

            if ($filter === 'paid_status') {
                $val = (int)$val;

                switch ($val) {
                    case Invoice::PAYMENT_STATUS_OPEN:
                    case Invoice::PAYMENT_STATUS_PAID:
                    case Invoice::PAYMENT_STATUS_PART:
                    case Invoice::PAYMENT_STATUS_CANCELED:
                    case Invoice::PAYMENT_STATUS_DEBIT:
                        break;

                    default:
                        continue;
                }
            }

            if ($filter === 'from' && is_numeric($val)) {
                $val = date('Y-m-d 00:00:00', $val);
            }

            if ($filter === 'to' && is_numeric($val)) {
                $val = date('Y-m-d 23:59:59', $val);
            }

            $this->filter[] = array(
                'filter' => $filter,
                'value'  => $val
            );
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = array();
    }

    /**
     * Set the limit
     *
     * @param string $from
     * @param string $to
     */
    public function limit($from, $to)
    {
        $this->limit = array((int)$from, (int)$to);
    }

    /**
     * Set the order
     *
     * @param $order
     */
    public function order($order)
    {
        switch ($order) {
            case 'id':
            case 'id ASC':
            case 'id DESC':
                $this->order = $order;
                break;

        }
    }

    /**
     * Execute the search and return the invoice list
     *
     * @return array
     */
    public function search()
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * Execute the search and return the invoice list for a grid control
     *
     * @return array
     * @throws QUI\Exception
     */
    public function searchForGrid()
    {
        $this->cache = array();

        // select display invoices
        $invoices = $this->executeQueryParams($this->getQuery());


        // count
        $count = $this->executeQueryParams($this->getQueryCount());
        $count = (int)$count[0]['count'];


        // total - calculation is without limit and paid_status
        $oldFiler = $this->filter;
        $oldLimit = $this->limit;

        $this->limit  = false;
        $this->filter = array_filter($this->filter, function ($filter) {
            return $filter['filter'] != 'paid_status';
        });

        $calc = $this->parseListForGrid($this->executeQueryParams($this->getQuery()));

        $this->filter = $oldFiler;
        $this->limit  = $oldLimit;


        // result
        $result = $this->parseListForGrid($invoices);
        $Grid   = new QUI\Utils\Grid();


        return array(
            'grid'  => $Grid->parseResult($result, $count),
            'total' => QUI\ERP\Accounting\Calc::calculateTotal($calc)
        );
    }

    /**
     * @return array
     */
    protected function getQueryCount()
    {
        return $this->getQuery(true);
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     */
    protected function getQuery($count = false)
    {
        $Invoices = Handler::getInstance();

        $table = $Invoices->invoiceTable();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end   = $this->limit[1];
            $limit = " LIMIT {$start},{$end}";
        }

        if (empty($this->filter)) {
            if ($count) {
                return array(
                    'query' => " SELECT COUNT(*)  AS count FROM {$table}",
                    'binds' => array()
                );
            }

            return array(
                'query' => "
                    SELECT id
                    FROM {$table}
                    ORDER BY {$order}
                    {$limit}
                ",
                'binds' => array()
            );
        }

        $where = array();
        $binds = array();
        $fc    = 0;

        foreach ($this->filter as $filter) {
            $bind = ':filter' . $fc;

            switch ($filter['filter']) {
                case 'from':
                    $where[] = 'date >= ' . $bind;
                    break;

                case 'to':
                    $where[] = 'date <= ' . $bind;
                    break;

                case 'paid_status':
                    if ((int)$filter['value'] === Invoice::PAYMENT_STATUS_OPEN) {
                        $bind1 = ':filter' . $fc;
                        $fc++;
                        $bind2 = ':filter' . $fc;

                        $where[] = '(paid_status = ' . $bind1 . ' OR paid_status = ' . $bind2 . ')';

                        $binds[$bind1] = array(
                            'value' => Invoice::PAYMENT_STATUS_OPEN,
                            'type'  => \PDO::PARAM_INT
                        );

                        $binds[$bind2] = array(
                            'value' => Invoice::PAYMENT_STATUS_PART,
                            'type'  => \PDO::PARAM_INT
                        );
                        continue;
                    }

                    $where[] = 'paid_status = ' . $bind;

                    $binds[$bind] = array(
                        'value' => (int)$filter['value'],
                        'type'  => \PDO::PARAM_INT
                    );
                    continue;

                default:
                    continue;
            }

            $binds[$bind] = array(
                'value' => $filter['value'],
                'type'  => \PDO::PARAM_STR
            );

            $fc++;
        }

        $whereQuery = 'WHERE ' . implode(' AND ', $where);


        if ($count) {
            return array(
                "query" => "
                    SELECT COUNT(*) AS count
                    FROM {$table}
                    {$whereQuery}
                ",
                'binds' => $binds
            );
        }

        return array(
            "query" => "
                SELECT id
                FROM {$table}
                {$whereQuery}
                ORDER BY {$order}
                {$limit}
            ",
            'binds' => $binds
        );
    }

    /**
     * @param array $queryData
     * @return array
     * @throws QUI\Exception
     */
    protected function executeQueryParams($queryData = array())
    {
        $PDO   = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeRecursive($Exception);
            QUI\System\Log::writeRecursive($query);
            QUI\System\Log::writeRecursive($binds);
            throw new QUI\Exception('Something went wrong');
        }
    }

    /**
     * @param array $data
     * @return array
     */
    protected function parseListForGrid($data)
    {
        $Invoices = Handler::getInstance();
        $Locale   = QUI::getLocale();
        $Payments = Payments::getInstance();

        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $DateFormatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );


        $needleFields = array(
            'customer_id',
            'customer_name',
            'comments',
            'c_user',
            'c_username',
            'date',
            'display_missing',
            'display_paid',
            'display_vatsum',
            'display_sum',
            'dunning_level',
            'hash',
            'id',
            'isbrutto',
            'nettosum',
            'order_id',
            'orderdate',
            'paidstatus',
            'paid_date',
            'processing',
            'payment_data',
            'payment_method',
            'payment_time',
            'payment_title',
            'processing_status',
            'sum',
            'taxId'
        );

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        $result = array();

        foreach ($data as $entry) {
            if (isset($this->cache[$entry['id']])) {
                $result[] = $this->cache[$entry['id']];
                continue;
            }

            try {
                $Invoice = $Invoices->getInvoice($entry['id']);
            } catch (QUI\Exception $Exception) {
                continue;
            }

            $Invoice->getPaidStatusInformation();

            $invoiceData = $Invoice->getAttributes();
            $fillFields($invoiceData);

            try {
                $currency = json_decode($Invoice->getAttribute('currency_data'), true);
                $Currency = Currencies::getCurrency($currency['code']);
            } catch (QUI\Exception $Exception) {
                $Currency = QUI\ERP\Defaults::getCurrency();
            }

            if (!$Invoice->getAttribute('order_id')) {
                $invoiceData['order_id'] = Handler::EMPTY_VALUE;
            }

            if (!$Invoice->getAttribute('dunning_level')) {
                $invoiceData['dunning_level'] = Invoice::DUNNING_LEVEL_OPEN;
            }


            $invoiceData['date']             = $DateFormatter->format(strtotime($Invoice->getAttribute('date')));
            $invoiceData['time_for_payment'] = $DateFormatter->format(strtotime($Invoice->getAttribute('time_for_payment')));

            if ($Invoice->getAttribute('paid_date')) {
                $invoiceData['paid_date'] = date('Y-m-d', $Invoice->getAttribute('paid_date'));
            } else {
                $invoiceData['paid_date'] = Handler::EMPTY_VALUE;
            }


            $invoiceData['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.' . $Invoice->getAttribute('paid_status')
            );

            $invoiceData['dunning_level_display'] = $Locale->get(
                'quiqqer/invoice',
                'dunning.level.' . $invoiceData['dunning_level']
            );

            try {
                $invoiceData['payment_title'] = $Payments->getPayment(
                    $Invoice->getAttribute('payment_method')
                )->getTitle();
            } catch (QUI\Exception $Exception) {
            }


            // data preparation
            if (!$Invoice->getAttribute('id_prefix')) {
                $invoiceData['id_prefix'] = Settings::getInstance()->getInvoicePrefix();
            }

            $invoiceData['id'] = $invoiceData['id_prefix'] . $invoiceData['id'];
            $invoiceAddress    = json_decode($invoiceData['invoice_address'], true);

            $invoiceData['customer_name'] = $invoiceAddress['salutation'] . ' ' .
                                            $invoiceAddress['firstname'] . ' ' .
                                            $invoiceAddress['lastname'];


            // display totals
            $invoiceData['display_nettosum'] = $Currency->format($invoiceData['nettosum']);
            $invoiceData['display_sum']      = $Currency->format($invoiceData['sum']);
            $invoiceData['display_subsum']   = $Currency->format($invoiceData['subsum']);
            $invoiceData['display_paid']     = $Currency->format($invoiceData['paid']);
            $invoiceData['display_toPay']    = $Currency->format($invoiceData['toPay']);

            $invoiceData['calculated_nettosum'] = $invoiceData['nettosum'];
            $invoiceData['calculated_sum']      = $invoiceData['sum'];
            $invoiceData['calculated_subsum']   = $invoiceData['subsum'];
            $invoiceData['calculated_paid']     = $invoiceData['paid'];
            $invoiceData['calculated_toPay']    = $invoiceData['toPay'];

            // vat information
            $vatArray = $invoiceData['vat_array'];
            $vatArray = json_decode($vatArray, true);

            $vat = array_map(function ($data) use ($Currency) {
                return $data['text'] . ': ' . $Currency->format($data['sum']);
            }, $vatArray);

            $vatSum = array_map(function ($data) use ($Currency) {
                return $data['sum'];
            }, $vatArray);

            $invoiceData['vat']               = implode('; ', $vat);
            $invoiceData['display_vatsum']    = $Currency->format(array_sum($vatSum));
            $invoiceData['calculated_vat']    = $vatSum;
            $invoiceData['calculated_vatsum'] = array_sum($vatSum);

            // customer data
            $customerData = json_decode($invoiceData['customer_data'], true);

            if (empty($customerData['erp.taxNumber'])) {
                $customerData['erp.taxNumber'] = Handler::EMPTY_VALUE;
            }

            $invoiceData['taxId'] = $customerData['erp.taxNumber'];

            // internal cache
            // wird genutzt damit calc und display nicht doppelt abfragen machen
            $this->cache[$entry['id']] = $invoiceData;

            $result[] = $invoiceData;
        }

        return $result;
    }
}
