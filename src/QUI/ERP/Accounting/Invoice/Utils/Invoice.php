<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Utils\Invoice
 */

namespace QUI\ERP\Accounting\Invoice\Utils;

use QUI;
use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;

/**
 * Class Invoice
 * Invoice Utils Helper
 *
 * @package QUI\ERP\Accounting\Invoice\Utils
 */
class Invoice
{
    /**
     * Tries to get an invoice by string
     *
     * @param $str
     * @return QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary
     *
     * @throws QUI\Exception
     */
    public static function getInvoiceByString($str)
    {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            return $Invoices->get($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getInvoiceByHash($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getInvoice($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getTemporaryInvoiceByHash($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getTemporaryInvoice($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        throw $Exception;
    }

    /**
     * Return all fields, attributes which are still missing to post the invoice
     *
     * @param InvoiceTemporary $Invoice
     * @return array
     */
    public static function getMissingAttributes(InvoiceTemporary $Invoice)
    {
        $Articles = $Invoice->getArticles();
        $Articles->calc();

        $missing = [];

        // address / customer fields
        $missing = array_merge(
            $missing,
            self::getMissingAddressFields($Invoice)
        );

        //articles
        if (!$Articles->count()) {
            $missing[] = 'article';
        }

        // payment
        try {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $Payments->getPayment($Invoice->getAttribute('payment_method'));
        } catch (QUI\ERP\Accounting\Payments\Exception $Exception) {
            $missing[] = 'payment';
        }

        $missing = array_unique($missing);

        return $missing;
    }

    /**
     * Return the missing fields
     * - if something is missing in the address
     *
     * @param InvoiceTemporary $Invoice
     * @return array
     */
    protected static function getMissingAddressFields(InvoiceTemporary $Invoice)
    {
        $address  = $Invoice->getAttribute('invoice_address');
        $missing  = [];
        $Customer = null;
        $Address  = null;

        $addressNeedles = [
            'firstname',
            'lastname',
            'street_no',
            'zip',
            'city',
            'country'
        ];

        if (!empty($address)) {
            $address = json_decode($address, true);

            foreach ($addressNeedles as $addressNeedle) {
                if (!isset($address[$addressNeedle])) {
                    $missing[] = 'invoice_address_'.$addressNeedle;
                    continue;
                }

                try {
                    self::verificateField($address[$addressNeedle]);
                } catch (QUI\Exception $Exception) {
                    $missing[] = 'invoice_address_'.$addressNeedle;
                }
            }

            return $missing;
        }

        $customerId = $Invoice->getAttribute('customer_id');
        $addressId  = $Invoice->getAttribute('invoice_address_id');

        //customer
        if (empty($customerId)) {
            $missing[] = 'customer_id';
        }

        try {
            $Customer = QUI::getUsers()->get($customerId);
        } catch (QUI\Exception $Exception) {
            $missing[] = 'customer_id';
        }

        try {
            if ($Customer) {
                $Address = $Customer->getAddress($addressId);
                $Address->getCountry(); // check address fields
            } else {
                $missing[] = 'invoice_address_id';
            }
        } catch (QUI\Exception $Exception) {
            $missing[] = 'invoice_address_id';
        }

        // address
        if (empty($addressId) && empty($address)) {
            $missing[] = 'invoice_address_id';
        }

        if ($Address) {
            foreach ($addressNeedles as $addressNeedle) {
                try {
                    self::verificateField($Address->getAttribute($addressNeedle));
                } catch (QUI\Exception $Exception) {
                    $missing[] = 'invoice_address_'.$addressNeedle;
                }
            }
        }

        return $missing;
    }

    /**
     * Return the correct message for a missing invoice attribute
     *
     * @param string $missingAttribute - name of the missing field / attribute
     * @return string
     * @throws Exception
     */
    public static function getMissingAttributeMessage($missingAttribute)
    {
        $Locale = QUI::getLocale();
        $lg     = 'quiqqer/invoice';

        switch ($missingAttribute) {
            case 'customer_id':
                return $Locale->get($lg, 'exception.invoice.verification.customer');

            case 'article':
                return $Locale->get($lg, 'exception.invoice.verification.empty.articles');

            case 'payment':
                return $Locale->get($lg, 'exception.invoice.verification.missingPayment');

            case 'invoice_address_id':
                return $Locale->get($lg, 'exception.invoice.verification.address');

            case 'invoice_address_firstname':
                return $Locale->get($lg, 'exception.invoice.verification.firstname');

            case 'invoice_address_lastname':
                return $Locale->get($lg, 'exception.invoice.verification.lastname');

            case 'invoice_address_street_no':
                return $Locale->get($lg, 'exception.invoice.verification.street_no');

            case 'invoice_address_zip':
                return $Locale->get($lg, 'exception.invoice.verification.zip');

            case 'invoice_address_city':
                return $Locale->get($lg, 'exception.invoice.verification.city');

            case 'invoice_address_country':
                return $Locale->get($lg, 'exception.invoice.verification.country');
        }

        throw new Exception('Missing Field not found: '.$missingAttribute);
    }

    /**
     * @param array|string $articles
     * @return array|string
     */
    public static function formatArticlesArray($articles)
    {
        $isString = is_string($articles);

        if ($isString) {
            $articles = json_decode($articles, true);
        }

        try {
            $currency = $articles['calculations']['currencyData'];
            $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
        } catch (\Exception $Exception) {
            $Currency = QUI\ERP\Defaults::getCurrency();
        }

        $fields = [
            'calculated_basisPrice',
            'calculated_price',
            'calculated_sum',
            'calculated_nettoBasisPrice',
            'calculated_nettoPrice',
            'calculated_nettoSubSum',
            'calculated_nettoSum',
            'unitPrice',
            'sum'
        ];

        foreach ($articles['articles'] as $key => $article) {
            foreach ($fields as $field) {
                if (isset($article[$field])) {
                    $articles['articles'][$key]['display_'.$field] = $Currency->format($article[$field]);
                }
            }
        }

        if ($isString) {
            return json_encode($articles);
        }

        return $articles;
    }

    /**
     * Verification of a field, value can not be empty
     *
     * @param $value
     * @param array|string $eMessage
     * @param int $eCode - optional
     * @param array $eContext - optional
     *
     * @throws Exception
     */
    protected static function verificateField($value, $eMessage = 'Error occurred', $eCode = 0, $eContext = [])
    {
        if (empty($value)) {
            throw new Exception($eMessage, $eCode, $eContext);
        }
    }

    /**
     * Return the file name for an invoice download
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @param QUI\Locale $Locale
     *
     * @return string
     */
    public static function getInvoiceFilename($Invoice, $Locale = null)
    {
        if (!($Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice) &&
            !($Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary)) {
            return '';
        }

        // date
        $localeCode = QUI::getLocale()->getLocalesByLang(
            QUI::getLocale()->getCurrent()
        );

        $Formatter = new \IntlDateFormatter(
            $localeCode[0],
            \IntlDateFormatter::SHORT,
            \IntlDateFormatter::NONE
        );

        $date = $Invoice->getAttribute('date');
        $date = strtotime($date);

        $year  = date('Y', $date);
        $month = date('m', $date);
        $day   = date('d', $date);

        $placeholders = [
            '%HASH%'  => $Invoice->getHash(),
            '%ID%'    => $Invoice->getCleanId(),
            '%INO%'   => $Invoice->getId(),
            '%DATE%'  => $Formatter->format($date),
            '%YEAR%'  => $year,
            '%MONTH%' => $month,
            '%DAY%'   => $day
        ];

        if ($Locale === null) {
            $Locale = $Invoice->getCustomer()->getLocale();
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $fileName = $Locale->get('quiqqer/invoice', 'pdf.download.name');

        foreach ($placeholders as $placeholder => $value) {
            $fileName = str_replace($placeholder, $value, $fileName);
        }

        return $fileName;
    }

    /**
     * General rounding
     *
     * @param float|int $amount
     * @return int|float
     */
    public static function roundInvoiceSum($amount)
    {
        return round($amount, 2);
    }

    /**
     * Return the time for payment date as unix timestamp
     *
     *
     * @param Invoice|InvoiceTemporary $Invoice
     * @return int - Unix Timestamp
     */
    public static function getInvoiceTimeForPaymentDate($Invoice)
    {
        $timeForPayment = $Invoice->getAttribute('time_for_payment');

        if ($Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
            $timeForPayment = (int)$timeForPayment;

            if ($timeForPayment) {
                $timeForPayment = strtotime('+'.$timeForPayment.' day');
            }
        } else {
            $timeForPayment = strtotime($timeForPayment);
        }

        return $timeForPayment;
    }

    /**
     * @param string|array $vatArray
     * @param QUI\ERP\Currency\Currency $Currency
     * @return array
     */
    public static function getVatTextArrayFromVatArray($vatArray, QUI\ERP\Currency\Currency $Currency)
    {
        if (is_string($vatArray)) {
            $vatArray = json_decode($vatArray, true);
        }

        if (!is_array($vatArray)) {
            $vatArray = [];
        }

        return array_map(function ($data) use ($Currency) {
            return $data['text'].': '.$Currency->format($data['sum']);
        }, $vatArray);
    }

    /**
     * @param string|array $vatArray
     * @return array
     */
    public static function getVatSumArrayFromVatArray($vatArray)
    {
        if (is_string($vatArray)) {
            $vatArray = json_decode($vatArray, true);
        }

        if (!is_array($vatArray)) {
            $vatArray = [];
        }

        return array_map(function ($data) {
            return $data['sum'];
        }, $vatArray);
    }

    /**
     * Return the vat sum from an var array from an invoice
     *
     * @param string|array $vatArray
     * @return int|float
     */
    public static function getVatSumFromVatArray($vatArray)
    {
        return array_sum(
            self::getVatSumArrayFromVatArray($vatArray)
        );
    }

    /**
     * Return all transactions of an invoice
     * or returns all transactions related to an invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|integer $Invoice - Invoice or Invoice ID
     * @return array
     */
    public static function getTransactionsByInvoice($Invoice)
    {
        if (!($Invoice instanceof QUI\ERP\Accounting\Invoice\Invoice)) {
            try {
                $Invoice = self::getInvoiceByString($Invoice);
            } catch (QUI\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);

                return [];
            }
        }

        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($Invoice->getHash());

        return $transactions;
    }
}
