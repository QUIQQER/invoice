<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Search\InvoiceSearch
 */

namespace QUI\ERP\Accounting\Invoice\Search;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Exception;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\ERP\Accounting\Invoice\Settings;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Payments as Payments;
use QUI\ERP\Currency\Handler as Currencies;
use QUI\Utils\Doctrine;
use QUI\Utils\Singleton;

use function array_flip;
use function array_map;
use function array_pad;
use function date;
use function explode;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function mb_strtolower;
use function strip_tags;
use function strlen;
use function strtotime;
use function substr;
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
     * @var list<array{filter: string, value: mixed}>
     */
    protected array $filter = [];

    /**
     * search value
     *
     * @var string
     */
    protected string $search = '';

    /**
     * @var array{int, int}|false
     */
    protected array | false $limit = [0, 20];

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
     * @var array<int|string, mixed>
     */
    protected array $cache = [];

    protected bool $calcTotal = true;

    /**
     * Set a filter
     *
     * @param string $filter
     * @param array<int|string, mixed>|string $value
     * @throws QUI\Exception
     */
    public function setFilter(string $filter, array | string $value): void
    {
        if ($filter === 'search') {
            /** @var string $value */
            $this->search = $value;

            return;
        }

        if ($filter === 'currency') {
            if (!is_string($value) || empty($value) || $value === '---') {
                $this->currency = QUI\ERP\Defaults::getCurrency()->getCode();

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
                $val = date('Y-m-d 00:00:00', (int)$val);
            }

            if ($filter === 'to' && is_numeric($val)) {
                $val = date('Y-m-d 23:59:59', (int)$val);
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
     * @param string $order
     */
    public function order(string $order): void
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
     * @return list<array<string, mixed>>
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
     * @return array<string, mixed>
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
        $page = 1;

        if ($this->limit !== false) {
            $page = ($this->limit[0] / $this->limit[1]) + 1;
        }

        $Grid = new QUI\Utils\Grid();
        $Grid->setAttribute('page', $page);

        $parsedInvoices = $this->parseListForGrid($invoices);
        $result['grid'] = $Grid->parseResult($parsedInvoices, $count);

        return $result;
    }

    /**
     * @return QueryBuilder
     * @throws QUI\Exception
     */
    protected function getQueryCount(): QueryBuilder
    {
        return $this->getQuery(true);
    }

    /**
     * @param bool $count - Use count select, or not
     * @return QueryBuilder
     * @throws QUI\Exception
     */
    protected function getQuery(bool $count = false): QueryBuilder
    {
        $table = Handler::getInstance()->invoiceTable();
        $QueryBuilder = QUI::getQueryBuilder()
            ->from(Doctrine::quoteIdentifier($table));

        if ($count) {
            $QueryBuilder->select('COUNT(' . Doctrine::quoteIdentifier('id') . ') AS count');
        } else {
            $QueryBuilder->select(Doctrine::quoteIdentifier('id'));
        }

        $hasSearchCriteria = !empty($this->filter) || !empty($this->search) || !empty($this->currency);

        if (!$hasSearchCriteria) {
            return $this->applyOrderAndLimit($QueryBuilder, $count);
        }

        $fc = 0;
        $DefaultCurrency = QUI\ERP\Defaults::getCurrency();

        if (empty($this->currency)) {
            $this->currency = $DefaultCurrency->getCode();
        }

        if ($DefaultCurrency->getCode() === $this->currency) {
            $QueryBuilder->andWhere($QueryBuilder->expr()->or(
                Doctrine::quoteIdentifier('currency') . ' = :currency',
                Doctrine::quoteIdentifier('currency') . " = ''",
                $QueryBuilder->expr()->isNull(Doctrine::quoteIdentifier('currency'))
            ));
        } else {
            $QueryBuilder->andWhere(Doctrine::quoteIdentifier('currency') . ' = :currency');
        }

        $QueryBuilder->setParameter('currency', $this->currency);

        foreach ($this->filter as $filter) {
            $parameter = 'filter' . $fc;
            $field = $filter['filter'];

            switch ($field) {
                case 'from':
                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier('date') . ' >= :' . $parameter);
                    $QueryBuilder->setParameter($parameter, $filter['value']);
                    break;

                case 'to':
                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier('date') . ' <= :' . $parameter);
                    $QueryBuilder->setParameter($parameter, $filter['value']);
                    break;

                case 'paid_status':
                    if ((int)$filter['value'] === QUI\ERP\Constants::PAYMENT_STATUS_OPEN) {
                        $openParameter = 'filter' . $fc;
                        $fc++;
                        $partParameter = 'filter' . $fc;

                        $QueryBuilder->andWhere($QueryBuilder->expr()->or(
                            Doctrine::quoteIdentifier('paid_status') . ' = :' . $openParameter,
                            Doctrine::quoteIdentifier('paid_status') . ' = :' . $partParameter
                        ));
                        $QueryBuilder->setParameter(
                            $openParameter,
                            QUI\ERP\Constants::PAYMENT_STATUS_OPEN
                        );
                        $QueryBuilder->setParameter(
                            $partParameter,
                            QUI\ERP\Constants::PAYMENT_STATUS_PART
                        );
                        break;
                    }

                    $QueryBuilder->andWhere(
                        Doctrine::quoteIdentifier('paid_status') . ' = :' . $parameter
                    );
                    $QueryBuilder->setParameter($parameter, (int)$filter['value']);
                    break;

                case 'customer_id':
                    $value = (string)$filter['value'];

                    if (QUI::getPackageManager()->isInstalled('quiqqer/customer')) {
                        $CustomerConfig = QUI::getpackage('quiqqer/customer')->getConfig();
                        $prefix = $CustomerConfig?->getValue('customer', 'customerNoPrefix');

                        if (is_string($prefix) && $prefix !== '' && str_starts_with($value, $prefix)) {
                            $value = substr($value, strlen($prefix));
                        }
                    }

                    $QueryBuilder->andWhere(
                        Doctrine::quoteIdentifier('customer_id') . ' = :' . $parameter
                    );
                    $QueryBuilder->setParameter($parameter, $value);
                    break;

                case 'id':
                case 'type':
                case 'isbrutto':
                case 'canceled':
                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier($field) . ' = :' . $parameter);
                    $QueryBuilder->setParameter($parameter, (int)$filter['value']);
                    break;

                default:
                    if (!in_array($field, $this->getAllowedFields(), true)) {
                        break;
                    }

                    $QueryBuilder->andWhere(Doctrine::quoteIdentifier($field) . ' = :' . $parameter);
                    $QueryBuilder->setParameter($parameter, $filter['value']);
            }

            $fc++;
        }

        if (!empty($this->search)) {
            $customerIdSearch = $this->search;

            if (QUI::getPackageManager()->isInstalled('quiqqer/customer')) {
                $CustomerConfig = QUI::getpackage('quiqqer/customer')->getConfig();
                $customerPrefix = $CustomerConfig?->getValue('customer', 'customerNoPrefix');

                if (
                    is_string($customerPrefix)
                    && $customerPrefix !== ''
                    && str_starts_with($customerIdSearch, $customerPrefix)
                ) {
                    $customerIdSearch = substr($customerIdSearch, strlen($customerPrefix));
                }
            }

            $invoicePrefix = Settings::getInstance()->getInvoicePrefix();
            $temporaryInvoicePrefix = Settings::getInstance()->getTemporaryInvoicePrefix();

            if ($invoicePrefix !== '' && str_starts_with($this->search, $invoicePrefix)) {
                $idSearch = substr($this->search, strlen($invoicePrefix));
            } elseif (
                $temporaryInvoicePrefix !== ''
                && str_starts_with($this->search, $temporaryInvoicePrefix)
            ) {
                $idSearch = substr($this->search, strlen($temporaryInvoicePrefix));
            } else {
                $idSearch = '%' . $this->search;
            }

            $Platform = QUI::getDataBaseConnection()->getDatabasePlatform();
            $castType = $Platform instanceof AbstractMySQLPlatform ? 'CHAR' : 'VARCHAR';
            $searchExpressions = [];

            foreach ($this->getSearchFields() as $field) {
                $searchParameter = match ($field) {
                    'id' => 'searchId',
                    'customer_id', 'customer_data' => 'customerIdSearch',
                    default => 'search'
                };
                $searchExpressions[] = 'LOWER(CAST(' . Doctrine::quoteIdentifier($field) .
                    ' AS ' . $castType . ')) LIKE :' . $searchParameter;
            }

            $QueryBuilder->andWhere($QueryBuilder->expr()->or(...$searchExpressions));
            $QueryBuilder->setParameter('search', '%' . mb_strtolower($this->search) . '%');
            $QueryBuilder->setParameter('searchId', mb_strtolower($idSearch) . '%');
            $QueryBuilder->setParameter(
                'customerIdSearch',
                '%' . mb_strtolower($customerIdSearch) . '%'
            );
        }

        return $this->applyOrderAndLimit($QueryBuilder, $count);
    }

    /**
     * @param QueryBuilder $QueryBuilder
     * @return list<array<string, mixed>>
     * @throws QUI\Exception
     */
    protected function executeQueryParams(QueryBuilder $QueryBuilder): array
    {
        try {
            return $QueryBuilder->executeQuery()->fetchAllAssociative();
        } catch (Exception $Exception) {
            QUI\System\Log::writeRecursive($Exception);
            QUI\System\Log::writeRecursive($QueryBuilder->getSQL());
            QUI\System\Log::writeRecursive($QueryBuilder->getParameters());
            throw new QUI\Exception('Something went wrong');
        }
    }

    private function applyOrderAndLimit(QueryBuilder $QueryBuilder, bool $count): QueryBuilder
    {
        if ($count) {
            return $QueryBuilder;
        }

        [$orderField, $orderDirection] = array_pad(explode(' ', trim($this->order), 2), 2, 'ASC');

        if (!in_array($orderField, $this->getAllowedFields(), true)) {
            $orderField = 'id';
            $orderDirection = 'DESC';
        }

        $QueryBuilder->orderBy(Doctrine::quoteIdentifier($orderField), strtoupper($orderDirection));

        if ($this->limit !== false) {
            $QueryBuilder->setFirstResult($this->limit[0]);
            $QueryBuilder->setMaxResults($this->limit[1]);
        }

        return $QueryBuilder;
    }

    /**
     * @return list<string>
     */
    private function getSearchFields(): array
    {
        return [
            'id',
            'customer_id',
            'hash',
            'global_process_id',
            'type',
            'order_id',
            'ordered_by',
            'ordered_by_name',
            'project_name',
            'invoice_address',
            'delivery_address',
            'service_period',
            'payment_time',
            'time_for_payment',
            'paid_status',
            'paid_date',
            'paid_data',
            'c_user',
            'c_date',
            'c_username',
            'editor_id',
            'editor_name',
            'data',
            'additional_invoice_text',
            'customer_data',
            'currency_data',
            'nettosum',
            'nettosubsum',
            'subsum',
            'sum'
        ];
    }

    /**
     * @param list<array<string, mixed>> $data
     * @return list<array<string, mixed>>
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
                        (int)strtotime($invoiceData['order_date']),
                        (string)$defaultTimeFormat
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
                (string)$defaultDateFormat
            );

            $invoiceData['time_for_payment'] = $Locale->formatDate(
                (int)$timeForPayment,
                (string)$defaultDateFormat
            );

            $invoiceData['c_date'] = $Locale->formatDate(
                strtotime($Invoice->getAttribute('c_date')),
                (string)$defaultTimeFormat
            );

            if ($Invoice->getAttribute('paid_date')) {
                $invoiceData['paid_date'] = $Locale->formatDate(
                    $Invoice->getAttribute('paid_date'),
                    (string)$defaultDateFormat
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
            } elseif ($Customer->getUUID()) {
                $invoiceData['customer_id_display'] = Handler::EMPTY_VALUE;
                $invoiceData['customer_id'] = $Customer->getUUID();
            } else {
                $invoiceData['customer_id_display'] = Handler::EMPTY_VALUE;
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
     * @return list<string>
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
            'service_period',

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
