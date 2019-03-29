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
use QUI\ERP\Accounting\Payments\Payments as Payments;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;

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
    protected $filter = [];

    /**
     * search value
     *
     * @var null
     */
    protected $search = null;

    /**
     * @var array
     */
    protected $limit = [0, 20];

    /**
     * @var string
     */
    protected $order = 'id DESC';

    /**
     * currency of the searched invoices
     *
     * @var string
     */
    protected $currency = '';

    /**
     * @var array
     */
    protected $cache = [];

    /**
     * Set a filter
     *
     * @param string $filter
     * @param string|array $value
     */
    public function setFilter($filter, $value)
    {
        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        if ($filter === 'currency') {
            if (empty($value)) {
                $this->currency = QUI\ERP\Currency\Handler::getDefaultCurrency()->getCode();

                return;
            }

            try {
                $allowed = QUI\ERP\Currency\Handler::getAllowedCurrencies();
            } catch (QUI\Exception $Exception) {
                return;
            }

            $allowed = \array_map(function ($Currency) {
                /* @var $Currency QUI\ERP\Currency\Currency */
                return $Currency->getCode();
            }, $allowed);

            $allowed = \array_flip($allowed);

            if (isset($allowed[$value])) {
                $this->currency = $value;
            }

            return;
        }

        $keys = \array_flip($this->getAllowedFields());

        if (!isset($keys[$filter]) && $filter !== 'from' && $filter !== 'to') {
            return;
        }

        if (!\is_array($value)) {
            $value = [$value];
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
                        continue 2;
                }
            }

            if (empty($val)
                && ($filter === 'from' || $filter === 'to')) {
                continue;
            }

            if ($filter === 'from' && \is_numeric($val)) {
                $val = \date('Y-m-d 00:00:00', $val);
            }

            if ($filter === 'to' && \is_numeric($val)) {
                $val = \date('Y-m-d 23:59:59', $val);
            }

            $this->filter[] = [
                'filter' => $filter,
                'value'  => $val
            ];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter()
    {
        $this->filter = [];
    }

    /**
     * Set the limit
     *
     * @param string $from
     * @param string $to
     */
    public function limit($from, $to)
    {
        $this->limit = [(int)$from, (int)$to];
    }

    /**
     * Set the order
     *
     * @param $order
     */
    public function order($order)
    {
        $allowed = [];

        foreach ($this->getAllowedFields() as $field) {
            $allowed[] = $field;
            $allowed[] = $field.' ASC';
            $allowed[] = $field.' asc';
            $allowed[] = $field.' DESC';
            $allowed[] = $field.' desc';
        }

        $order   = \trim($order);
        $allowed = \array_flip($allowed);

        if (isset($allowed[$order])) {
            $this->order = $order;
        }
    }

    /**
     * Execute the search and return the invoice list
     *
     * @return array
     *
     * @throws QUI\Exception
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
        $this->cache = [];

        // select display invoices
        $invoices = $this->executeQueryParams($this->getQuery());

        // count
        $count = $this->executeQueryParams($this->getQueryCount());
        $count = (int)$count[0]['count'];


        // quiqqer/invoice#38
        // total - calculation is without limit
        $oldLimit = $this->limit;

        $this->limit = false;
        $calc        = $this->parseListForGrid($this->executeQueryParams($this->getQuery()));

        //$this->filter = $oldFiler;
        $this->limit = $oldLimit;


        // result
        $result = $this->parseListForGrid($invoices);
        $Grid   = new QUI\Utils\Grid();


        return [
            'grid'  => $Grid->parseResult($result, $count),
            'total' => QUI\ERP\Accounting\Calc::calculateTotal($calc)
        ];
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

        if (empty($this->filter) && empty($this->search)) {
            if ($count) {
                return [
                    'query' => " SELECT COUNT(*)  AS count FROM {$table}",
                    'binds' => []
                ];
            }

            return [
                'query' => "
                    SELECT id
                    FROM {$table}
                    ORDER BY {$order}
                    {$limit}
                ",
                'binds' => []
            ];
        }

        // filter
        $where = [];
        $binds = [];
        $fc    = 0;

        // currency
        $DefaultCurrency = QUI\ERP\Currency\Handler::getDefaultCurrency();

        if (empty($this->currency)) {
            $this->currency = $DefaultCurrency->getCode();
        }

        // fallback for old invoices
        if ($DefaultCurrency->getCode() === $this->currency) {
            $where[] = "(currency = :currency OR currency = '')";
        } else {
            $where[] = 'currency = :currency';
        }

        $binds[':currency'] = [
            'value' => $this->currency,
            'type'  => \PDO::PARAM_STR
        ];


        // filter
        foreach ($this->filter as $filter) {
            $bind = ':filter'.$fc;
            $flr  = $filter['filter'];

            switch ($flr) {
                case 'from':
                    $where[] = 'date >= '.$bind;
                    break;

                case 'to':
                    $where[] = 'date <= '.$bind;
                    break;

                case 'paid_status':
                    if ((int)$filter['value'] === Invoice::PAYMENT_STATUS_OPEN) {
                        $bind1 = ':filter'.$fc;
                        $fc++;
                        $bind2 = ':filter'.$fc;

                        $where[] = '(paid_status = '.$bind1.' OR paid_status = '.$bind2.')';

                        $binds[$bind1] = [
                            'value' => Invoice::PAYMENT_STATUS_OPEN,
                            'type'  => \PDO::PARAM_INT
                        ];

                        $binds[$bind2] = [
                            'value' => Invoice::PAYMENT_STATUS_PART,
                            'type'  => \PDO::PARAM_INT
                        ];

                        break;
                    }

                    $where[] = 'paid_status = '.$bind;

                    $binds[$bind] = [
                        'value' => (int)$filter['value'],
                        'type'  => \PDO::PARAM_INT
                    ];

                    break;

                case 'customer_id':
                case 'c_user':
                case 'id':
                case 'order_id':
                case 'taxId':
                case 'hash':
                case 'isbrutto':
                    $where[] = $flr.' = '.$bind;

                    $binds[$bind] = [
                        'value' => (int)$filter['value'],
                        'type'  => \PDO::PARAM_INT
                    ];

                    break;
            }

            $binds[$bind] = [
                'value' => $filter['value'],
                'type'  => \PDO::PARAM_STR
            ];

            $fc++;
        }

        if (!empty($this->search)) {
            $where[] = '(
                id LIKE :search OR
                customer_id LIKE :search OR
                hash LIKE :search OR
                global_process_id LIKE :search OR
                type LIKE :search OR
                order_id LIKE :search OR
                ordered_by LIKE :search OR
                ordered_by_name LIKE :search OR
                project_name LIKE :search OR
                invoice_address LIKE :search OR
                delivery_address LIKE :search OR
                payment_time LIKE :search OR
                time_for_payment LIKE :search OR
                paid_status LIKE :search OR
                paid_date LIKE :search OR
                paid_data LIKE :search OR
                c_user LIKE :search OR
                c_date LIKE :search OR
                c_username LIKE :search OR
                editor_id LIKE :search OR
                editor_name LIKE :search OR
                data LIKE :search OR
                additional_invoice_text LIKE :search OR
                customer_data LIKE :search OR
                currency_data LIKE :search OR
                nettosum LIKE :search OR
                nettosubsum LIKE :search OR
                subsum LIKE :search OR
                sum LIKE :search
            )';

            $binds['search'] = [
                'value' => '%'.$this->search.'%',
                'type'  => \PDO::PARAM_STR
            ];
        }

        $whereQuery = 'WHERE '.\implode(' AND ', $where);

        if (!\count($where)) {
            $whereQuery = '';
        }

        if ($count) {
            return [
                "query" => "
                    SELECT COUNT(*) AS count
                    FROM {$table}
                    {$whereQuery}
                ",
                'binds' => $binds
            ];
        }

        return [
            "query" => "
                SELECT id
                FROM {$table}
                {$whereQuery}
                ORDER BY {$order}
                {$limit}
            ",
            'binds' => $binds
        ];
    }

    /**
     * @param array $queryData
     * @return array
     * @throws QUI\Exception
     */
    protected function executeQueryParams($queryData = [])
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
     *
     * @throws QUI\Exception
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

        $needleFields = [
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
            'order_date',
            'paid_status',
            'paid_date',
            'processing',
            'payment_data',
            'payment_method',
            'payment_time',
            'payment_title',
            'processing_status',
            'sum',
            'taxId'
        ];

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        $result = [];

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
                $currency = \json_decode($Invoice->getAttribute('currency_data'), true);
                $Currency = Currencies::getCurrency($currency['code']);
            } catch (QUI\Exception $Exception) {
                $Currency = QUI\ERP\Defaults::getCurrency();
            }

            // order
            try {
                $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById(
                    $invoiceData['order_id']
                );

                $invoiceData['order_date'] = $Order->getCreateDate();
            } catch (QUI\Exception $Exception) {
            }

            if (!$Invoice->getAttribute('order_id')) {
                $invoiceData['order_id'] = Handler::EMPTY_VALUE;
            }

            if (!$Invoice->getAttribute('dunning_level')) {
                $invoiceData['dunning_level'] = Invoice::DUNNING_LEVEL_OPEN;
            }


            $timeForPayment = \strtotime($Invoice->getAttribute('time_for_payment'));

            $invoiceData['globalProcessId']  = $Invoice->getGlobalProcessId();
            $invoiceData['date']             = $DateFormatter->format(\strtotime($Invoice->getAttribute('date')));
            $invoiceData['time_for_payment'] = $DateFormatter->format($timeForPayment);

            if ($Invoice->getAttribute('paid_date')) {
                $invoiceData['paid_date'] = \date('Y-m-d', $Invoice->getAttribute('paid_date'));
            } else {
                $invoiceData['paid_date'] = Handler::EMPTY_VALUE;
            }


            $invoiceData['paid_status_display'] = $Locale->get(
                'quiqqer/invoice',
                'payment.status.'.$Invoice->getAttribute('paid_status')
            );

            $invoiceData['dunning_level_display'] = $Locale->get(
                'quiqqer/invoice',
                'dunning.level.'.$invoiceData['dunning_level']
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

            $invoiceData['id'] = $invoiceData['id_prefix'].$invoiceData['id'];
            $invoiceAddress    = \json_decode($invoiceData['invoice_address'], true);

            $invoiceData['customer_name'] = $invoiceAddress['salutation'].' '.
                                            $invoiceAddress['firstname'].' '.
                                            $invoiceAddress['lastname'];

            if (!empty($invoiceAddress['company'])) {
                $invoiceData['customer_name'] = trim($invoiceData['customer_name']);
                $invoiceData['customer_name'] = $invoiceAddress['company'].' ('.$invoiceData['customer_name'].')';
            }

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
            $vatTextArray = InvoiceUtils::getVatTextArrayFromVatArray($invoiceData['vat_array'], $Currency);
            $vatSumArray  = InvoiceUtils::getVatSumArrayFromVatArray($invoiceData['vat_array']);
            $vatSum       = InvoiceUtils::getVatSumFromVatArray($invoiceData['vat_array']);

            $invoiceData['vat']               = $vatTextArray;
            $invoiceData['display_vatsum']    = $Currency->format($vatSum);
            $invoiceData['calculated_vat']    = $vatSumArray;
            $invoiceData['calculated_vatsum'] = $vatSum;

            // customer data
            $customerData = \json_decode($invoiceData['customer_data'], true);

            if (empty($customerData['erp.taxId'])) {
                $customerData['erp.taxId'] = Handler::EMPTY_VALUE;
            }

            $invoiceData['taxId'] = $customerData['erp.taxId'];

            // overdue check
            if (\time() > $timeForPayment &&
                $Invoice->getAttribute('paid_status') != Invoice::PAYMENT_STATUS_PAID &&
                $Invoice->getAttribute('paid_status') != Invoice::PAYMENT_STATUS_CANCELED
            ) {
                $invoiceData['overdue'] = 1;
            }

            // internal cache
            // wird genutzt damit calc und display nicht doppelt abfragen machen
            $this->cache[$entry['id']] = $invoiceData;

            $result[] = $invoiceData;
        }

        return $result;
    }

    /**
     * @return array
     */
    protected function getAllowedFields()
    {
        return [
            'id',
            'id_prefix',
            'customer_id',
            'type',

            'order_id',
            'ordered_by',
            'ordered_by_name',

            'hash',
            'project_name',
            'date',

            'invoice_address',
            'delivery_address',

            'payment_method',
            'payment_method_data',
            'payment_data',
            'payment_time',
            'time_for_payment',

            'paid_status',
            'paid_date',
            'paid_data',

            'canceled',
            'canceled_data',
            'c_user',
            'c_username',
            'editor_id',
            'editor_name',
            'data',
            'additional_invoice_text',
            'articles',
            'history',
            'comments',
            'customer_data',
            'isbrutto',

            'currency_data',
            'currency',

            'nettosum',
            'nettosubsum',
            'subsum',
            'sum',
            'vat_array'
        ];
    }
}
