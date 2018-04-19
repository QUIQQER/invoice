<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Utils\Invoice
 */

namespace QUI\ERP\Accounting\Invoice\Utils;

use QUI;
use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;

/**
 * Class Invoice
 * Invoice Utils Helper
 *
 * @package QUI\ERP\Accounting\Invoice\Utils
 */
class Invoice
{
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

        $currency = $articles['calculations']['currencyData'];

        try {
            $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
        } catch (QUI\Exception $Exception) {
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
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @return string
     */
    public static function getInvoiceFilename($Invoice)
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

        $fileName = QUI::getLocale()->get('quiqqer/invoice', 'pdf.download.name');

        foreach ($placeholders as $placeholder => $value) {
            $fileName = str_replace($placeholder, $value, $fileName);
        }

        return $fileName;
    }
}
