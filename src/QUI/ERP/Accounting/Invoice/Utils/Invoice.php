<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Utils\Invoice
 */

namespace QUI\ERP\Accounting\Invoice\Utils;

use DateTime;
use QUI;
use QUI\ERP\Accounting\Invoice\Exception;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatuses;
use QUI\ERP\Currency\Currency;
use QUI\ExceptionStack;
use QUI\ERP\Defaults;
use IntlDateFormatter;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use horstoeko\zugferd\codelists\ZugferdCountryCodes;
use horstoeko\zugferd\codelists\ZugferdCurrencyCodes;
use horstoeko\zugferd\codelists\ZugferdElectronicAddressScheme;
use horstoeko\zugferd\codelists\ZugferdInvoiceType;
use horstoeko\zugferd\codelists\ZugferdReferenceCodeQualifiers;
use horstoeko\zugferd\codelists\ZugferdUnitCodes;
use horstoeko\zugferd\codelists\ZugferdVatCategoryCodes;
use horstoeko\zugferd\codelists\ZugferdVatTypeCodes;

use function array_map;
use function array_merge;
use function array_search;
use function array_sum;
use function array_unique;
use function date;
use function in_array;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function str_replace;
use function strtotime;

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
     * @param int|string $str
     * @return QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary
     *
     * @throws QUI\Exception
     */
    public static function getInvoiceByString(int | string $str): QUI\ERP\Accounting\Invoice\Invoice | InvoiceTemporary
    {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            return $Invoices->get($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getInvoiceByHash((string)$str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return $Invoices->getInvoice($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        try {
            return self::getTemporaryInvoiceByString($str);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        throw $Exception;
    }

    /**
     * @param int|string $str
     *
     * @return InvoiceTemporary
     *
     * @throws Exception
     * @throws QUI\Exception
     */
    public static function getTemporaryInvoiceByString(int | string $str): InvoiceTemporary
    {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            return $Invoices->getTemporaryInvoiceByHash((string)$str);
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
     * @return array<int, string>
     *
     * @throws ExceptionStack
     * @throws QUI\Exception
     */
    public static function getMissingAttributes(InvoiceTemporary $Invoice): array
    {
        $Articles = $Invoice->getArticles();
        $Articles->calc();

        $missing = [];

        // address / customer fields
        $missing = array_merge(
            $missing,
            self::getMissingAddressFields($Invoice)
        );

        // articles
        if (!$Articles->count()) {
            $missing[] = 'article';
        }

        // payment
        try {
            $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
            $Payments->getPayment($Invoice->getAttribute('payment_method'));
        } catch (QUI\Exception) {
            $missing[] = 'payment';
        }

        // Status that prevents posting
        $statusId = $Invoice->getAttribute('processing_status');

        if (!empty($statusId)) {
            try {
                $Status = ProcessingStatuses::getInstance()->getProcessingStatus(
                    $statusId
                );

                if ($Status->getOption(ProcessingStatuses::STATUS_OPTION_PREVENT_INVOICE_POSTING)) {
                    $missing[] = 'status_prevents_posting';
                }
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // api
        QUI::getEvents()->fireEvent('onQuiqqerInvoiceMissingAttributes', [$Invoice, &$missing]);

        return array_unique($missing);
    }

    /**
     * Return the missing fields
     * - if something is missing in the address
     *
     * @param InvoiceTemporary $Invoice
     * @return array<int, string>
     *
     * @throws QUI\Exception
     * @todo better address check
     */
    protected static function getMissingAddressFields(InvoiceTemporary $Invoice): array
    {
        $address = $Invoice->getAttribute('invoice_address');
        $missing = [];
        $Customer = null;
        $Address = null;

        $addressRequired = self::addressRequirement();
        $addressThreshold = self::addressRequirementThreshold();
        $addressNeedles = [];
        $Calculation = $Invoice->getPriceCalculation();

        if ($addressRequired === false && $Calculation->getSum()->value() > $addressThreshold) {
            $addressRequired = true;
        }


        if ($addressRequired) {
            $addressNeedles = [
                'lastname',
                'street_no',
                'city',
                'country'
            ];

            if (!empty($address)) {
                return self::getMissingAddressData(json_decode($address, true));
            }
        }

        $customerId = $Invoice->getAttribute('customer_id');
        $addressId = $Invoice->getAttribute('invoice_address_id');

        if ($Invoice->getCustomer() === null) {
            $customerId = false;
            $addressId = false;
        }

        //customer
        if (empty($customerId)) {
            $missing[] = 'customer_id';
        }

        if ($customerId) {
            try {
                $Customer = QUI::getUsers()->get($customerId);
            } catch (QUI\Exception) {
                if (
                    $Invoice->getInvoiceType() !== QUI\ERP\Constants::TYPE_INVOICE_REVERSAL
                    || $Invoice->getCustomer() === null
                ) {
                    $missing[] = 'customer_id';
                }
            }
        }

        if ($addressRequired) {
            try {
                if ($Customer) {
                    $Address = $Customer->getAddress($addressId);
                    $Address->getCountry(); // check address fields
                } else {
                    $missing[] = 'invoice_address_id';
                }
            } catch (QUI\Exception) {
                $missing[] = 'invoice_address_id';
            }
        }

        // address
        if ($addressRequired && empty($addressId) && empty($address)) {
            $missing[] = 'invoice_address_id';
        }

        if ($Address) {
            foreach ($addressNeedles as $addressNeedle) {
                try {
                    self::verificateField($Address->getAttribute($addressNeedle));
                } catch (QUI\Exception) {
                    $missing[] = 'invoice_address_' . $addressNeedle;
                }
            }
        }

        // company check
        // @todo better company check
        if ($Customer && $Customer->isCompany() && in_array('invoice_address_lastname', $missing)) {
            unset($missing[array_search('invoice_address_lastname', $missing)]);
        }

        return $missing;
    }

    /**
     * @throws Exception
     * @throws ExceptionStack
     */
    public static function checkAddress(QUI\Users\Address $Address): void
    {
        $missing = self::getMissingAddressData($Address->getAttributes());

        if (!empty($missing)) {
            throw new Exception(self::getMissingAttributeMessage($missing[0]));
        }
    }

    /**
     * @param array<string, mixed> $address
     * @return list<string>
     */
    public static function getMissingAddressData(array $address): array
    {
        $missing = [];

        $addressNeedles = [
            'lastname',
            'street_no',
            'city',
            'country'
        ];

        if (empty($address['lastname']) && !empty($address['company'])) {
            $address['lastname'] = $address['company'];
        }

        foreach ($addressNeedles as $addressNeedle) {
            if (!isset($address[$addressNeedle])) {
                $missing[] = 'invoice_address_' . $addressNeedle;
                continue;
            }

            try {
                self::verificateField($address[$addressNeedle]);
            } catch (QUI\Exception) {
                $missing[] = 'invoice_address_' . $addressNeedle;
            }
        }

        return $missing;
    }

    /**
     * Return the correct message for a missing invoice attribute
     *
     * @param string $missingAttribute - name of the missing field / attribute
     * @return string
     * @throws Exception|ExceptionStack
     */
    public static function getMissingAttributeMessage(string $missingAttribute): string
    {
        $Locale = QUI::getLocale();
        $lg = 'quiqqer/invoice';

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

            case 'status_prevents_posting':
                return $Locale->get($lg, 'exception.invoice.verification.status_prevents_posting');
        }

        $message = false;

        QUI::getEvents()->fireEvent(
            'onQuiqqerInvoiceGetMissingAttributeMessage',
            [$missingAttribute, &$message]
        );

        /* @phpstan-ignore-next-line */
        if (!empty($message)) {
            return $message;
        }

        throw new Exception('Missing Field not found: ' . $missingAttribute);
    }

    /**
     * @param array<string, mixed>|string $articles
     * @return array<string, mixed>|string
     */
    public static function formatArticlesArray(array | string $articles): array | string
    {
        $isString = is_string($articles);

        if ($isString) {
            $articles = json_decode($articles, true);
        }

        try {
            $currency = $articles['calculations']['currencyData'];
            $Currency = QUI\ERP\Currency\Handler::getCurrency($currency['code']);
        } catch (\Exception) {
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
                    $articles['articles'][$key]['display_' . $field] = $Currency->format($article[$field]);
                }
            }
        }

        if ($isString) {
            return (string)json_encode($articles);
        }

        return $articles;
    }

    /**
     * Verification of a field, value can not be empty
     *
     * @param mixed $value
     * @param array<int|string, mixed>|string $eMessage
     * @param int $eCode - optional
     * @param array<int|string, mixed> $eContext - optional
     *
     * @throws Exception
     */
    protected static function verificateField(
        mixed $value,
        array | string $eMessage = 'Error occurred',
        int $eCode = 0,
        array $eContext = []
    ): void {
        if (empty($value)) {
            throw new Exception($eMessage, $eCode, $eContext);
        }
    }

    /**
     * Return invoice placeholders used in configurable invoice strings
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @param QUI\Locale|null $Locale
     *
     * @return array<string, string>
     */
    public static function getInvoicePlaceholders(
        QUI\ERP\Accounting\Invoice\Invoice | InvoiceTemporary $Invoice,
        ?QUI\Locale $Locale = null
    ): array {
        if ($Locale === null) {
            try {
                $Locale = $Invoice->getCustomer()->getLocale();
            } catch (QUI\ERP\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $localeCode = $Locale->getLocalesByLang($Locale->getCurrent());

        $Formatter = new IntlDateFormatter(
            $localeCode[0],
            IntlDateFormatter::SHORT,
            IntlDateFormatter::NONE
        );

        $date = strtotime((string)$Invoice->getAttribute('date'));

        if (!$date) {
            $date = time();
        }

        $company = '';
        $firstname = '';
        $lastname = '';
        $customerName = '';
        $customerNo = '';
        $customerId = '';

        try {
            $Customer = $Invoice->getCustomer();
            $Address = $Customer->getAddress();

            $company = trim((string)$Address->getAttribute('company'));
            $firstname = trim((string)$Address->getAttribute('firstname'));
            $lastname = trim((string)$Address->getAttribute('lastname'));
            $customerName = trim($Customer->getInvoiceName());
            $customerNo = trim($Customer->getCustomerNo());
            $customerId = trim($Customer->getUUID());
        } catch (QUI\ERP\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $formattedDate = $Formatter->format($date);

        if ($formattedDate === false) {
            $formattedDate = '';
        }

        return [
            '%HASH%' => (string)$Invoice->getUUID(),
            '%ID%' => (string)$Invoice->getCleanId(),
            '%INO%' => (string)$Invoice->getPrefixedNumber(),
            '%DATE%' => (string)$formattedDate,
            '%YEAR%' => date('Y', $date),
            '%MONTH%' => date('m', $date),
            '%DAY%' => date('d', $date),
            '%CUSTOMER_ID%' => $customerId,
            '%CUSTOMER_NO%' => $customerNo,
            '%CUSTOMER_NAME%' => $customerName,
            '%COMPANY%' => $company,
            '%FIRSTNAME%' => $firstname,
            '%LASTNAME%' => $lastname
        ];
    }

    /**
     * Return the file name for an invoice download
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @param QUI\Locale|null $Locale
     *
     * @return string
     * @throws QUI\Exception
     */
    public static function getInvoiceFilename(
        QUI\ERP\Accounting\Invoice\Invoice | InvoiceTemporary $Invoice,
        ?QUI\Locale $Locale = null
    ): string {
        if ($Locale === null) {
            try {
                $Locale = $Invoice->getCustomer()->getLocale();
            } catch (QUI\ERP\Exception $Exception) {
                QUI\System\Log::writeDebugException($Exception);
            }
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        /** @var string $fileName */
        $fileName = $Locale->get('quiqqer/invoice', 'pdf.download.name');

        foreach (self::getInvoicePlaceholders($Invoice, $Locale) as $placeholder => $value) {
            $fileName = str_replace($placeholder, $value, $fileName);
        }

        /** @var string $fileName */
        $fileName = QUI\Utils\Security\Orthos::clearFilename($fileName);

        return $fileName;
    }

    /**
     * Return the configured buyer email fallback for electronic invoices.
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @return string
     */
    protected static function getElectronicInvoiceBuyerEmailFallback(
        QUI\ERP\Accounting\Invoice\Invoice | InvoiceTemporary $Invoice
    ): string {
        $buyerEmail = '';

        try {
            $buyerEmail = (string)QUI::getPackage('quiqqer/invoice')
                ->getConfig()
                ->getValue('invoice', 'electronicInvoiceBuyerEmailFallback');
        } catch (\Throwable $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        $buyerEmail = trim($buyerEmail);

        if ($buyerEmail === '') {
            $buyerEmail = 'unknown@example.com';
        }

        foreach (self::getInvoicePlaceholders($Invoice) as $placeholder => $value) {
            $buyerEmail = str_replace($placeholder, $value, $buyerEmail);
        }

        $buyerEmail = trim($buyerEmail);

        if ($buyerEmail === '') {
            return 'unknown@example.com';
        }

        return $buyerEmail;
    }

    /**
     * General rounding
     *
     * @param float|int $amount
     * @param Currency|null $Currency
     * @return int|float
     */
    public static function roundInvoiceSum(
        float | int $amount,
        ?QUI\ERP\Currency\Currency $Currency = null
    ): float | int {
        if ($Currency === null) {
            $Currency = QUI\ERP\Defaults::getCurrency();

            QUI\System\Log::addError('Währung fehlt bei Invoice::roundInvoiceSum() ... bitte beheben', [
                'stack' => debug_backtrace()
            ]);
        }

        return round($amount, $Currency->getPrecision());
    }

    /**
     * Return the time for payment date as unix timestamp
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|InvoiceTemporary $Invoice
     * @return int - Unix Timestamp
     */
    public static function getInvoiceTimeForPaymentDate(
        InvoiceTemporary | QUI\ERP\Accounting\Invoice\Invoice $Invoice
    ): int {
        $timeForPayment = $Invoice->getAttribute('time_for_payment');

        if ($Invoice instanceof QUI\ERP\Accounting\Invoice\InvoiceTemporary) {
            $timeForPayment = (int)$timeForPayment;

            if ($timeForPayment >= 0) {
                $timeForPayment = strtotime('+' . $timeForPayment . ' day');
            }
        } else {
            $timeForPayment = strtotime($timeForPayment);
        }

        return $timeForPayment;
    }

    /**
     * @param array<int|string, mixed>|string $vatArray
     * @param QUI\ERP\Currency\Currency $Currency
     * @return array<int|string, string>
     */
    public static function getVatTextArrayFromVatArray(
        array | string $vatArray,
        QUI\ERP\Currency\Currency $Currency
    ): array {
        if (is_string($vatArray)) {
            $vatArray = json_decode($vatArray, true);
        }

        if (!is_array($vatArray)) {
            $vatArray = [];
        }

        return array_map(function ($data) use ($Currency) {
            return $data['text'] . ': ' . $Currency->format($data['sum']);
        }, $vatArray);
    }

    /**
     * @param array<int|string, mixed>|string $vatArray
     * @return array<int|string, mixed>
     */
    public static function getVatSumArrayFromVatArray(array | string $vatArray): array
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
     * Return the vat sum from a var array of an invoice
     *
     * @param array<int|string, mixed>|string|null $vatArray
     * @return int|float
     */
    public static function getVatSumFromVatArray(array | string | null $vatArray): float | int
    {
        if (empty($vatArray)) {
            return 0;
        }

        return array_sum(
            self::getVatSumArrayFromVatArray($vatArray)
        );
    }

    /**
     * Return all transactions of an invoice
     * or returns all transactions related to an invoice
     *
     * @param QUI\ERP\Accounting\Invoice\Invoice|integer|string $Invoice - Invoice or Invoice ID
     * @return array<int|string, mixed>
     */
    public static function getTransactionsByInvoice(QUI\ERP\Accounting\Invoice\Invoice | int | string $Invoice): array
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
        return $Transactions->getTransactionsByHash($Invoice->getUUID());
    }

    /**
     * Are addresses for invoices mandatory?
     *
     * @return bool
     * @throws QUI\Exception
     */
    public static function addressRequirement(): bool
    {
        return !!QUI::getPackage('quiqqer/invoice')->getConfig()->get('invoice', 'invoiceAddressRequirement');
    }

    /**
     * Maximum invoice gross total up to which an invoice address is not required.
     * This applies only if a general invoice address requirement is disabled.
     *
     * @return float
     * @throws QUI\Exception
     */
    public static function addressRequirementThreshold(): float
    {
        $threshold = QUI::getPackage('quiqqer/invoice')->getConfig()->get(
            'invoice',
            'invoiceAddressRequirementThreshold'
        );

        if (empty($threshold)) {
            return 0;
        }

        return floatval($threshold);
    }

    /**
     * Normalizes the invoice delivery date / service period input.
     *
     * @throws \InvalidArgumentException
     */
    public static function normalizeServicePeriod(mixed $value): string
    {
        if (empty($value)) {
            return '';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_array($value)) {
            $type = $value['type'] ?? '';

            if ($type === 'date' && !empty($value['date'])) {
                $date = self::parseServicePeriodDate($value['date']);

                return (string)json_encode([
                    'type' => 'date',
                    'date' => $date->format('Y-m-d')
                ]);
            }

            if ($type === 'period' && !empty($value['start']) && !empty($value['end'])) {
                $start = self::parseServicePeriodDate($value['start']);
                $end = self::parseServicePeriodDate($value['end']);

                if ($end < $start) {
                    throw new \InvalidArgumentException('The service period end date must not be before the start date.');
                }

                return (string)json_encode([
                    'type' => 'period',
                    'start' => $start->format('Y-m-d'),
                    'end' => $end->format('Y-m-d')
                ]);
            }

            return '';
        }

        $value = trim((string)$value);

        if ($value === '') {
            return '';
        }

        $parts = (array)preg_split('/\s+-\s+/', $value);

        if (count($parts) === 2) {
            $start = self::parseServicePeriodDate((string)$parts[0]);
            $end = self::parseServicePeriodDate((string)$parts[1]);

            if ($end < $start) {
                throw new \InvalidArgumentException('The service period end date must not be before the start date.');
            }

            return (string)json_encode([
                'type' => 'period',
                'start' => $start->format('Y-m-d'),
                'end' => $end->format('Y-m-d')
            ]);
        }

        $date = self::parseServicePeriodDate($value);

        return (string)json_encode([
            'type' => 'date',
            'date' => $date->format('Y-m-d')
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getServicePeriodData(
        InvoiceTemporary | QUI\ERP\Accounting\Invoice\Invoice $Invoice
    ): array {
        $servicePeriod = $Invoice->getAttribute('service_period');

        if (empty($servicePeriod)) {
            return [];
        }

        if (is_string($servicePeriod)) {
            $servicePeriod = json_decode($servicePeriod, true);
        }

        if (!is_array($servicePeriod)) {
            return [];
        }

        return $servicePeriod;
    }

    public static function getServicePeriodDisplayText(
        InvoiceTemporary | QUI\ERP\Accounting\Invoice\Invoice $Invoice,
        ?QUI\Locale $Locale = null
    ): string {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $data = self::getServicePeriodData($Invoice);

        if (($data['type'] ?? '') === 'date' && !empty($data['date'])) {
            return $Locale->get('quiqqer/invoice', 'service.period.display.date', [
                'date' => self::formatServicePeriodDate($data['date'], $Locale)
            ]);
        }

        if (($data['type'] ?? '') === 'period' && !empty($data['start']) && !empty($data['end'])) {
            return $Locale->get('quiqqer/invoice', 'service.period.display.period', [
                'start' => self::formatServicePeriodDate($data['start'], $Locale),
                'end' => self::formatServicePeriodDate($data['end'], $Locale)
            ]);
        }

        return $Locale->get('quiqqer/invoice', 'service.period.display.default');
    }

    protected static function parseServicePeriodDate(string $date): DateTime
    {
        $date = trim($date);
        $formats = ['Y-m-d', 'd.m.Y', 'd.m.y'];

        foreach ($formats as $format) {
            $Date = DateTime::createFromFormat('!' . $format, $date);

            if ($Date && $Date->format($format) === $date) {
                return $Date;
            }
        }

        throw new \InvalidArgumentException('Invalid service period date.');
    }

    protected static function formatServicePeriodDate(string $date, QUI\Locale $Locale): string
    {
        return (string)$Locale->getDateFormatter()->format((int)strtotime($date));
    }

    /**
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     * @throws QUI\Users\Exception
     */
    public static function getElectronicInvoice(
        InvoiceTemporary | QUI\ERP\Accounting\Invoice\Invoice $Invoice,
        int $type = ZugferdProfiles::PROFILE_EN16931
    ): ZugferdDocumentBuilder {
        $document = ZugferdDocumentBuilder::CreateNew($type);
        $Articles = $Invoice->getArticles();

        $date = $Invoice->getAttribute('date');
        $date = strtotime($date);
        $date = (new DateTime())->setTimestamp($date);

        $invoiceType = $Invoice->getInvoiceType();
        $isCreditNote = $invoiceType === QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE;
        $documentType = match ($invoiceType) {
            QUI\ERP\Constants::TYPE_INVOICE,
            QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY => ZugferdInvoiceType::INVOICE,
            QUI\ERP\Constants::TYPE_INVOICE_CREDIT_NOTE => ZugferdInvoiceType::CREDITNOTE,
            default => ZugferdInvoiceType::INVOICE
        };

        $normalizeAmount = static function (float | int $amount) use ($isCreditNote): float | int {
            return $isCreditNote ? abs($amount) : $amount;
        };

        $document->setDocumentInformation(
            $Invoice->getPrefixedNumber(),
            $documentType,
            $date,
            $Invoice->getCurrency()->getCode()
        );

        $servicePeriod = self::getServicePeriodData($Invoice);

        if (($servicePeriod['type'] ?? '') === 'period') {
            $document->setDocumentBillingPeriod(
                self::parseServicePeriodDate($servicePeriod['start']),
                self::parseServicePeriodDate($servicePeriod['end']),
                null
            );
        } elseif (($servicePeriod['type'] ?? '') === 'date') {
            $document->setDocumentSupplyChainEvent(self::parseServicePeriodDate($servicePeriod['date']));
        } else {
            $document->setDocumentSupplyChainEvent($date);
        }

        // ids
        $taxId = Defaults::conf('company', 'taxId');
        $taxNumber = Defaults::conf('company', 'taxNumber');

        // seller / owner
        $companyName = Defaults::conf('company', 'name');
        /** @var string $companyName */
        $document->setDocumentSeller($companyName);

        // @todo global seller id
        //  ->addDocumentSellerGlobalId("4000001123452", "0088");

        if (!empty($taxNumber)) {
            $document->addDocumentSellerTaxRegistration("FC", $taxNumber);
        }

        if (!empty($taxId)) {
            $document->addDocumentSellerTaxRegistration("VA", $taxId);
        }

        // address
        $country = Defaults::conf('company', 'country');
        /** @var string $country */

        if (strlen($country) !== 2) {
            $DefaultLocale = QUI::getSystemLocale();

            foreach (QUI\Countries\Manager::getCompleteList() as $Country) {
                if ($Country->getName($DefaultLocale) === $country) {
                    $country = $Country->getCode();
                    break;
                }
            }
        }

        if (strlen($country) !== 2) {
            $country = '';
        }
        $street = Defaults::conf('company', 'street');
        $zipCode = Defaults::conf('company', 'zipCode');
        $city = Defaults::conf('company', 'city');
        /** @var string $street */
        /** @var string $zipCode */
        /** @var string $city */

        $document->setDocumentSellerAddress(
            $street,
            "",
            "",
            $zipCode,
            $city,
            $country
        );

        $email = Defaults::conf('company', 'email');
        /** @var string $email */
        $document->setDocumentSellerCommunication(
            ZugferdElectronicAddressScheme::UNECE3155_EM,
            $email
        );

        $owner = Defaults::conf('company', 'owner');
        $phone = Defaults::conf('company', 'phone');
        $fax = Defaults::conf('company', 'fax');
        /** @var string $owner */
        /** @var string $phone */
        /** @var string $fax */
        $document->setDocumentSellerContact(
            $owner, // @todo contact person
            '',     // @todo contact department
            $phone, // @todo contact phone
            $fax,   // @todo contact fax
            $email  // @todo contact email
        );

        // bank stuff
        $bankAccount = QUI\ERP\BankAccounts\Handler::getCompanyBankAccount();
        $payment = $Invoice->getPayment();
        $paymentType = $payment->getPaymentType();
        $paymentTypeCode = $payment->getTypeCode();

        $payeeIban = $bankAccount['iban'] ?? '';
        $payeeIban = str_replace(' ', '', $payeeIban);
        $payeeIban = trim($payeeIban);

        // sepa
        $buyerIban = '';

        if (
            class_exists('QUI\ERP\Payments\SEPA\Payment')
            && class_exists('QUI\ERP\Payments\SEPA\Transactions')
            && QUI\ERP\Payments\SEPA\Payment::class === $paymentType
        ) {
            $paymentData = QUI\ERP\Payments\SEPA\Transactions::parsePaymentData($Invoice->getCustomer(), $Invoice);

            $document->addDocumentPaymentMeanToDirectDebit(
                $paymentData['account']['iban'],
                $paymentData['account']['id']
            );

            $buyerIban = $paymentData['account']['iban'];
            $buyerIban = str_replace(' ', '', $buyerIban);
            $buyerIban = trim($buyerIban);
        }


        $document->addDocumentPaymentMean(
            $paymentTypeCode->value,        // typeCode
            $payment->getTitle(),       // information
            null,                  // cardType
            null,                    // cardId
            null,            // cardHolderName
            $buyerIban,                    // buyerIban
            $payeeIban,                    // payeeIban (Empfänger)
            $bankAccount['name'] ?? '', // payeeAccountName
            null,                    // payeePropId
            $bankAccount['bic'] ?? ''   // payeeBic
        );

        // customer
        $Customer = $Invoice->getCustomer();

        $document
            ->setDocumentBuyer($Customer->getInvoiceName(), $Customer->getCustomerNo())
            ->setDocumentBuyerAddress(
                $Customer->getAddress()->getAttribute('street_no'),
                "",
                "",
                $Customer->getAddress()->getAttribute('zip'),
                $Customer->getAddress()->getAttribute('city'),
                $Customer->getAddress()->getCountry()->getCode()
            )->setDocumentBuyerReference($Customer->getUUID());


        $buyerEmail = trim((string)$Customer->getAddress()->getAttribute('email'));

        if ($buyerEmail === '') {
            try {
                $User = QUI::getUsers()->get($Customer->getUUID());
                $buyerEmail = trim((string)$User->getAttribute('email'));
            } catch (QUI\Exception) {
                $buyerEmail = '';
            }
        }

        if ($buyerEmail === '') {
            $buyerEmail = self::getElectronicInvoiceBuyerEmailFallback($Invoice);
        }

        $document->setDocumentBuyerCommunication(
            ZugferdElectronicAddressScheme::UNECE3155_EM,
            $buyerEmail
        );

        //->setDocumentBuyerOrderReferencedDocument($Invoice->getUUID());

        // total
        $priceCalculation = $Invoice->getPriceCalculation();
        $vatTotal = 0;

        foreach ($priceCalculation->getVat() as $vat) {
            $vatValue = $normalizeAmount($vat->value());

            $document->addDocumentTax(
                "S",
                "VAT",
                $normalizeAmount($priceCalculation->getNettoSum()->value()),
                $vatValue,
                $vat->getVat()
            );

            $vatTotal = $vatTotal + $vatValue;
        }

        $isNetInvoice = false;

        if ($Customer->getAttribute('isNetto') || $Articles->getCalculations()['isNetto']) {
            $isNetInvoice = true;
        }

        $document->setDocumentSummation(
            $normalizeAmount($priceCalculation->getSum()->value()),
            $normalizeAmount($priceCalculation->getSum()->value()),
            $normalizeAmount($priceCalculation->getNettoSum()->value()),
            0.0, // zuschläge
            0.0, // rabatte
            $normalizeAmount($priceCalculation->getNettoSum()->value()), // Steuerbarer Betrag (BT-109)
            $isNetInvoice ? $vatTotal : 0, // ausgewiesene steuer
            null, // Rundungsbetrag
            0.0 // Vorauszahlungen
        );

        // products
        foreach ($Articles as $Article) {
            $article = $Article->toArray();

            $nettoPreis = $article['calculated']['nettoPrice']; // Netto-Einzelpreis
            $vatSum = $article['calculated']['vatArray']['sum'];
            $bruttoPreis = $nettoPreis;

            if ($vatSum) {
                $bruttoPreis = $nettoPreis + ($vatSum / $article['quantity']);
            }


            $document
                ->addNewPosition($article['position'])
                ->setDocumentPositionProductDetails(
                    $article['title'],
                    $article['description'],
                    null,
                    null,
                    null,
                    null
                )
                ->setDocumentPositionNetPrice($normalizeAmount($article['calculated']['nettoPrice']), 1, "C62") // C62 = Stück
                ->setDocumentPositionGrossPrice($normalizeAmount($bruttoPreis), 1, "C62") // C62 = Stück
                ->setDocumentPositionQuantity($article['quantity'], "H87")
                // Do not pass the position tax amount as 4th parameter here:
                // ->addDocumentPositionTax('S', 'VAT', $article['vat'], $article['calculated']['vatArray']['sum'])
                // The 4th parameter writes ram:CalculatedAmount. For line-level VAT taxes this is obsolete
                // and rejected by EN16931/XRechnung validators (CII-SR-182). Tax amounts are declared on document level.
                ->addDocumentPositionTax('S', 'VAT', $article['vat'])
                ->setDocumentPositionLineSummation($normalizeAmount($article['sum']));
        }

        // payment stuff
        $PaymentDate = null;

        try {
            $timeForPayment = $Invoice->getAttribute('time_for_payment');
            $timeForPayment = strtotime($timeForPayment);

            if ($timeForPayment) {
                $PaymentDate = new DateTime();
                $PaymentDate->setTimestamp($timeForPayment);
            }
        } catch (\Exception) {
        }

        $document->addDocumentPaymentTerm(
            $Invoice->getAttribute('additional_invoice_text'),
            $PaymentDate
        );

        return $document;
    }
}
