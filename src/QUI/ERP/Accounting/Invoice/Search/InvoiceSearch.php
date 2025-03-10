<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Search\InvoiceSearch
 */

namespace QUI\ERP\Accounting\Invoice\Search;

use Exception;
use PDO;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Payments as Payments;
use QUI\ERP\Currency\Handler as Currencies;
use QUI\Utils\Singleton;

use function array_flip;
use function array_map;
use function count;
use function date;
use function implode;
use function is_array;
use function is_numeric;
use function json_decode;
use function strip_tags;
use function strlen;
use function strtotime;
use function substr;
use function substr_replace;
use function time;
use function trim;

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
    protected array $filter = [];

    /**
     * search value
     *
     * @var string
     */
    protected string $search = '';

    /**
     * @var array|bool
     */
    protected array | bool $limit = [0, 20];

    /**
     * @var string
     */
    protected string $order = 'id DESC';

    /**
     * currency of the searched invoices
     *
     * @var string
     */
    protected string $currency = '';

    /**
     * @var array
     */
    protected array $cache = [];

    protected bool $calcTotal = true;

    /**
     * Set a filter
     *
     * @param string $filter
     * @param array|string $value
     * @throws QUI\Exception
     */
    public function setFilter(string $filter, array | string $value): void
    {
        if ($filter === 'search') {
            $this->search = $value;

            return;
        }

        if ($filter === 'currency') {
            if (empty($value) || $value === '---') {
                $this->currency = QUI\ERP\Currency\Handler::getDefaultCurrency()->getCode();

                return;
            }

            $allowed = QUI\ERP\Currency\Handler::getAllowedCurrencies();
            $allowed = array_map(function ($Currency) {
                return $Currency->getCode();
            }, $allowed);

            $allowed = array_flip($allowed);

            if (isset($allowed[$value])) {
                $this->currency = $value;
            }

            return;
        }

        $keys = array_flip($this->getAllowedFields());

        if (!isset($keys[$filter]) && $filter !== 'from' && $filter !== 'to') {
            return;
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        foreach ($value as $val) {
            if ($filter === 'paid_status' && $val === '') {
                continue;
            }

            if ($filter === 'paid_status') {
                $val = (int)$val;

                switch ($val) {
                    case QUI\ERP\Constants::PAYMENT_STATUS_OPEN:
                    case QUI\ERP\Constants::PAYMENT_STATUS_PAID:
                    case QUI\ERP\Constants::PAYMENT_STATUS_PART:
                    case QUI\ERP\Constants::PAYMENT_STATUS_CANCELED:
                    case QUI\ERP\Constants::PAYMENT_STATUS_DEBIT:
                        break;

                    default:
                        continue 2;
                }
            }

            if (
                empty($val)
                && ($filter === 'from' || $filter === 'to')
            ) {
                continue;
            }

            if ($filter === 'from' && is_numeric($val)) {
                $val = date('Y-m-d 00:00:00', $val);
            }

            if ($filter === 'to' && is_numeric($val)) {
                $val = date('Y-m-d 23:59:59', $val);
            }

            $this->filter[] = [
                'filter' => $filter,
                'value' => $val
            ];
        }
    }

    /**
     * Clear all filters
     */
    public function clearFilter(): void
    {
        $this->filter = [];
    }

    /**
     * Enables the calculation of the total value.
     *
     * @return void
     */
    public function enableCalcTotal(): void
    {
        $this->calcTotal = true;
    }

    /**
     * Disables the calculation of the total value.
     *
     * @return void
     */
    public function disableCalcTotal(): void
    {
        $this->calcTotal = false;
    }

    /**
     * Set the limit
     *
     * @param int|string $from
     * @param int|string $to
     */
    public function limit(int | string $from, int | string $to): void
    {
        $this->limit = [(int)$from, (int)$to];
    }

    /**
     * Set no limit
     * return all results
     */
    public function noLimit(): void
    {
        $this->limit = false;
    }

    /**
     * Set the order
     *
     * @param $order
     */
    public function order($order): void
    {
        $allowed = [];

        if (str_contains($order, 'display_sum')) {
            $order = str_replace('display_sum', 'sum', $order);
        }

        if (str_contains($order, 'display_nettosum')) {
            $order = str_replace('display_nettosum', 'nettosum', $order);
        }

        foreach ($this->getAllowedFields() as $field) {
            $allowed[] = $field;
            $allowed[] = $field . ' ASC';
            $allowed[] = $field . ' asc';
            $allowed[] = $field . ' DESC';
            $allowed[] = $field . ' desc';
        }

        $order = trim($order);
        $allowed = array_flip($allowed);

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
    public function search(): array
    {
        return $this->executeQueryParams($this->getQuery());
    }

    /**
     * Execute the search and return the invoice list for a grid control
     *
     * @return array
     * @throws QUI\Exception
     */
    public function searchForGrid(): array
    {
        $this->cache = [];

        // select display invoices
        $query = $this->getQuery();
        $countQuery = $this->getQueryCount();

        $invoices = $this->executeQueryParams($query);

        // count
        $count = $this->executeQueryParams($countQuery);
        $count = (int)$count[0]['count'];

        // currency
        $Currency = null;

        if (!empty($this->currency) && $this->currency !== '---') {
            try {
                $Currency = QUI\ERP\Currency\Handler::getCurrency($this->currency);
            } catch (QUI\Exception) {
            }
        }

        $result = [];

        // total calculation
        if ($this->calcTotal) {
            // quiqqer/invoice#38
            // total - calculation is without limit
            $oldLimit = $this->limit;

            $this->limit = false;
            $calc = $this->parseListForGrid($this->executeQueryParams($this->getQuery()));

            //$this->filter = $oldFiler;
            $this->limit = $oldLimit;

            $result['total'] = QUI\ERP\Accounting\Calc::calculateTotal($calc, $Currency);
        } else {
            $result['total'] = QUI\ERP\Accounting\Calc::calculateTotal([], $Currency);
        }

        // grid data
        $Grid = new QUI\Utils\Grid();
        $Grid->setAttribute('page', ($this->limit[0] / $this->limit[1]) + 1);

        $parsedInvoices = $this->parseListForGrid($invoices);
        $result['grid'] = $Grid->parseResult($parsedInvoices, $count);

        return $result;
    }

    /**
     * @return array
     * @throws QUI\Exception
     */
    protected function getQueryCount(): array
    {
        return $this->getQuery(true);
    }

    /**
     * @param bool $count - Use count select, or not
     * @return array
     * @throws QUI\Exception
     */
    protected function getQuery(bool $count = false): array
    {
        $Invoices = Handler::getInstance();

        $table = $Invoices->invoiceTable();
        $order = $this->order;

        // limit
        $limit = '';

        if ($this->limit && isset($this->limit[0]) && isset($this->limit[1])) {
            $start = $this->limit[0];
            $end = $this->limit[1];
            $limit = " LIMIT $start,$end";
        }

        if (empty($this->filter) && empty($this->search)) {
            if ($count) {
                return [
                    'query' => " SELECT COUNT(*)  AS count FROM $table",
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
        $fc = 0;

        // currency
        $DefaultCurrency = QUI\ERP\Currency\Handler::getDefaultCurrency();

        if (empty($this->currency)) {
            $this->currency = $DefaultCurrency->getCode();
        }

        // fallback for old invoices
        if ($DefaultCurrency->getCode() === $this->currency) {
            $where[] = "(currency = :currency OR currency = '' OR currency IS NULL)";
        } else {
            $where[] = 'currency = :currency';
        }

        $binds[':currency'] = [
            'value' => $this->currency,
            'type' => PDO::PARAM_STR
        ];

        // filter
        foreach ($this->filter as $filter) {
            $bind = ':filter' . $fc;
            $flr = $filter['filter'];

            switch ($flr) {
                case 'from':
                    $where[] = 'date >= ' . $bind;
                    break;

                case 'to':
                    $where[] = 'date <= ' . $bind;
                    break;

                case 'paid_status':
                    if ((int)$filter['value'] === QUI\ERP\Constants::PAYMENT_STATUS_OPEN) {
                        $bind1 = ':filter' . $fc;
                        $fc++;
                        $bind2 = ':filter' . $fc;

                        $where[] = '(paid_status = ' . $bind1 . ' OR paid_status = ' . $bind2 . ')';

                        $binds[$bind1] = [
                            'value' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                            'type' => PDO::PARAM_INT
                        ];

                        $binds[$bind2] = [
                            'value' => QUI\ERP\Constants::PAYMENT_STATUS_PART,
                            'type' => PDO::PARAM_INT
                        ];

                        break;
                    }

                    $where[] = 'paid_status = ' . $bind;

                    $binds[$bind] = [
                        'value' => (int)$filter['value'],
                        'type' => PDO::PARAM_INT
                    ];

                    break;

                case 'customer_id':
                    $value = (string)$filter['value'];
                    $where[] = $flr . ' = ' . $bind;

                    // remove customer prefix, for better search
                    if (QUI::getPackageManager()->isInstalled('quiqqer/customer')) {
                        $prefix = QUI::getpackage('quiqqer/customer')->getConfig()->getValue(
                            'customer',
                            'customerNoPrefix'
                        );

                        if (str_starts_with($value, $prefix)) {
                            $value = substr_replace($value, '', 0, strlen($value));
                        }
                    }

                    $binds[$bind] = [
                        'value' => (int)$value,
                        'type' => PDO::PARAM_INT
                    ];

                    break;

                case 'c_user':
                case 'id':
                case 'order_id':
                case 'taxId':
                case 'hash':
                case 'isbrutto':
                    $where[] = $flr . ' = ' . $bind;

                    $binds[$bind] = [
                        'value' => (int)$filter['value'],
                        'type' => PDO::PARAM_INT
                    ];

                    break;
            }

            $binds[$bind] = [
                'value' => $filter['value'],
                'type' => PDO::PARAM_STR
            ];

            $fc++;
        }

        if (!empty($this->search)) {
            $customerIdSearch = $this->search;

            // remove customer prefix, for better search
            if (QUI::getPackageManager()->isInstalled('quiqqer/customer')) {
                $prefix = QUI::getpackage('quiqqer/customer')->getConfig()->getValue(
                    'customer',
                    'customerNoPrefix'
                );

                if (str_starts_with($customerIdSearch, $prefix)) {
                    $customerIdSearch = substr_replace($customerIdSearch, '', 0, strlen($prefix));
                }
            }

            $binds['customerIdSearch'] = [
                'value' => '%' . $customerIdSearch . '%',
                'type' => PDO::PARAM_STR
            ];

            $where[] = '(
                id LIKE :searchId OR
                customer_id LIKE :customerIdSearch OR
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
                customer_data LIKE :customerIdSearch OR
                currency_data LIKE :search OR
                nettosum LIKE :search OR
                nettosubsum LIKE :search OR
                subsum LIKE :search OR
                sum LIKE :search
            )';

            $prefix = Settings::getInstance()->getInvoicePrefix();
            $tempPrefix = Settings::getInstance()->getTemporaryInvoicePrefix();

            if (str_starts_with($this->search, $prefix)) {
                $idSearch = substr($this->search, strlen($prefix));

                $binds['searchId'] = [
                    'value' => $idSearch . '%',
                    'type' => PDO::PARAM_STR
                ];
            } elseif (str_starts_with($this->search, $tempPrefix)) {
                $idSearch = substr($this->search, strlen($tempPrefix));

                $binds['searchId'] = [
                    'value' => $idSearch . '%',
                    'type' => PDO::PARAM_STR
                ];
            } else {
                $binds['searchId'] = [
                    'value' => '%' . $this->search . '%',
                    'type' => PDO::PARAM_STR
                ];
            }

            $binds['search'] = [
                'value' => '%' . $this->search . '%',
                'type' => PDO::PARAM_STR
            ];
        }

        $whereQuery = 'WHERE ' . implode(' AND ', $where);

        if (!count($where)) {
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
    protected function executeQueryParams(array $queryData = []): array
    {
        $PDO = QUI::getDataBase()->getPDO();
        $binds = $queryData['binds'];
        $query = $queryData['query'];

        $Statement = $PDO->prepare($query);

        foreach ($binds as $var => $bind) {
            $Statement->bindValue($var, $bind['value'], $bind['type']);
        }

        try {
            $Statement->execute();

            return $Statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $Exception) {
            QUI\System\Log::writeRecursive($Exception);
            QUI\System\Log::writeRecursive($query);
            QUI\System\Log::writeRecursive($binds);
            throw new QUI\Exception('Something went wrong');
        }
    }

    /**
     * @param array $data
     * @return array
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     */
    protected function parseListForGrid(array $data): array
    {
        $Invoices = Handler::getInstance();
        $Locale = QUI::getLocale();
        $Payments = Payments::getInstance();

        $defaultDateFormat = QUI\ERP\Defaults::getDateFormat();
        $defaultTimeFormat = QUI\ERP\Defaults::getTimestampFormat();

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
            'processing_status_display',
            'sum',
            'taxId',
            'project_name'
        ];

        $fillFields = function (&$data) use ($needleFields) {
            foreach ($needleFields as $field) {
                if (!isset($data[$field])) {
                    $data[$field] = Handler::EMPTY_VALUE;
                }
            }
        };

        // processing status stuff
        $ProcessingStatus = ProcessingStatusHandler::getInstance();
        $list = $ProcessingStatus->getProcessingStatusList();
        $processing = [];

        foreach ($list as $Status) {
            $processing[$Status->getId()] = $Status;
        }


        $result = [];

        foreach ($data as $entry) {
            if (isset($this->cache[$entry['id']])) {
                $result[] = $this->cache[$entry['id']];
                continue;
            }

            try {
                $Invoice = $Invoices->getInvoice($entry['id']);
            } catch (QUI\Exception) {
                continue;
            }

            $Invoice->getPaidStatusInformation();

            $Customer = $Invoice->getCustomer();
            $invoiceData = $Invoice->getAttributes();

            $fillFields($invoiceData);

            try {
                $currency = json_decode($Invoice->getAttribute('currency_data'), true);
                $Currency = Currencies::getCurrency($currency['code']);
            } catch (QUI\Exception) {
                $Currency = QUI\ERP\Defaults::getCurrency();
            }

            // order
            try {
                if (QUI::getPackageManager()->isInstalled('quiqqer/order')) {
                    $Order = QUI\ERP\Order\Handler::getInstance()->getOrderById(
                        $invoiceData['order_id']
                    );

                    $invoiceData['order_date'] = $Order->getCreateDate();
                    $invoiceData['order_date'] = $Locale->formatDate(
                        strtotime($invoiceData['order_date']),
                        $defaultTimeFormat
                    );
                } else {
                    $invoiceData['order_date'] = Handler::EMPTY_VALUE;
                }
            } catch (QUI\Exception) {
            }

            if (!$Invoice->getAttribute('order_id')) {
                $invoiceData['order_id'] = Handler::EMPTY_VALUE;
            }

            if (!$Invoice->getAttribute('dunning_level')) {
                $invoiceData['dunning_level'] = Invoice::DUNNING_LEVEL_OPEN;
            }


            $timeForPayment = strtotime($Invoice->getAttribute('time_for_payment'));

            $invoiceData['globalProcessId'] = $Invoice->getGlobalProcessId();

            $invoiceData['date'] = $Locale->formatDate(
                strtotime($Invoice->getAttribute('date')),
                $defaultDateFormat
            );

            $invoiceData['time_for_payment'] = $Locale->formatDate($timeForPayment, $defaultDateFormat);

            $invoiceData['c_date'] = $Locale->formatDate(
                strtotime($Invoice->getAttribute('c_date')),
                $defaultTimeFormat
            );

            if ($Invoice->getAttribute('paid_date')) {
                $invoiceData['paid_date'] = $Locale->formatDate(
                    $Invoice->getAttribute('paid_date'),
                    $defaultDateFormat
                );
            } else {
                $invoiceData['paid_date'] = Handler::EMPTY_VALUE;
            }

            $paidStatus = $Invoice->getAttribute('paid_status');
            $textStatus = $Locale->get(
                'quiqqer/erp',
                'payment.status.' . $paidStatus
            );

            $invoiceData['paid_status'] = $textStatus;
            $invoiceData['paid_status_display'] = '<span class="payment-status payment-status-' . $paidStatus . '">' . $textStatus . '</span>';
            $invoiceData['paid_status_clean'] = strip_tags($textStatus);

            $invoiceData['dunning_level_display'] = $Locale->get(
                'quiqqer/invoice',
                'dunning.level.' . $invoiceData['dunning_level']
            );

            try {
                $invoiceData['payment_title'] = $Payments->getPayment(
                    $Invoice->getAttribute('payment_method')
                )->getTitle();
            } catch (QUI\Exception) {
            }

            // data preparation
            if (!$Invoice->getAttribute('id_prefix')) {
                $invoiceData['id_prefix'] = Settings::getInstance()->getInvoicePrefix();
            }

            $invoiceAddress = json_decode($invoiceData['invoice_address'], true);

            if (!isset($invoiceAddress['salutation'])) {
                $invoiceAddress['salutation'] = '';
            }

            if ($Customer->getAttribute('customerId')) {
                $invoiceData['customer_id_display'] = $Customer->getAttribute('customerId');
                $invoiceData['customer_id'] = $Customer->getUUID() ?: $Customer->getId();
            } else {
                $invoiceData['customer_id_display'] = '';
                $invoiceData['customer_id'] = '';
            }

            $invoiceData['customer_name'] = trim(
                $invoiceAddress['salutation'] . ' ' .
                $invoiceAddress['firstname'] . ' ' .
                $invoiceAddress['lastname']
            );

            if (!empty($invoiceAddress['company'])) {
                $invoiceData['customer_name'] = trim($invoiceData['customer_name']);

                if (!empty($invoiceData['customer_name'])) {
                    $invoiceData['customer_name'] = $invoiceAddress['company'] . ' (' . $invoiceData['customer_name'] . ')';
                } else {
                    $invoiceData['customer_name'] = $invoiceAddress['company'];
                }
            }

            // processing status
            $processStatus = $invoiceData['processing_status'];

            if (isset($processing[$processStatus])) {
                /* @var $Status QUI\ERP\Accounting\Invoice\ProcessingStatus\Status */
                $Status = $processing[$processStatus];
                $color = $Status->getColor();

                $invoiceData['processing_status_display'] = '<span class="processing-status" style="color: ' . $color . '">' .
                    $Status->getTitle() . '</span>';
            }

            // if status is paid = invoice is paid
            $invoiceData['paid_status'] = $Invoice->getAttribute('paid_status');

            // display totals
            $invoiceData['display_nettosum'] = $Currency->format($invoiceData['nettosum']);
            $invoiceData['display_sum'] = $Currency->format($invoiceData['sum']);
            $invoiceData['display_subsum'] = $Currency->format($invoiceData['subsum']);
            $invoiceData['display_paid'] = $Currency->format($invoiceData['paid']);
            $invoiceData['display_toPay'] = $Currency->format($invoiceData['toPay']);


            $invoiceData['calculated_nettosum'] = $invoiceData['nettosum'];
            $invoiceData['calculated_sum'] = $invoiceData['sum'];
            $invoiceData['calculated_subsum'] = $invoiceData['subsum'];
            $invoiceData['calculated_paid'] = $invoiceData['paid'];
            $invoiceData['calculated_toPay'] = $invoiceData['toPay'];

            // vat information
            $vatTextArray = InvoiceUtils::getVatTextArrayFromVatArray($invoiceData['vat_array'], $Currency);
            $vatSumArray = InvoiceUtils::getVatSumArrayFromVatArray($invoiceData['vat_array']);
            $vatSum = InvoiceUtils::getVatSumFromVatArray($invoiceData['vat_array']);

            $invoiceData['vat'] = $vatTextArray;
            $invoiceData['display_vatsum'] = $Currency->format($vatSum);
            $invoiceData['calculated_vat'] = $vatSumArray;
            $invoiceData['calculated_vatsum'] = $vatSum;

            // customer data
            $customerData = json_decode($invoiceData['customer_data'], true);

            if (empty($customerData['erp.taxId'])) {
                $customerData['erp.taxId'] = Handler::EMPTY_VALUE;
            }

            $invoiceData['taxId'] = $customerData['erp.taxId'];

            // overdue check
            if (
                time() > $timeForPayment &&
                $Invoice->getAttribute('paid_status') != QUI\ERP\Constants::PAYMENT_STATUS_PAID &&
                $Invoice->getAttribute('paid_status') != QUI\ERP\Constants::PAYMENT_STATUS_CANCELED
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
    protected function getAllowedFields(): array
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
