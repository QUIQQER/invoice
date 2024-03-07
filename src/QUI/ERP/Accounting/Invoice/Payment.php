<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\Payment
 */

namespace QUI\ERP\Accounting\Invoice;

use QUI;

use function reset;

/**
 * Class Payment
 * - This class represent an invoice payment
 * - This is not a payment method or payment type, its only represent the data for the invoice
 *
 * @package QUI\ERP\Accounting\Invoice
 */
class Payment
{
    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * Payment constructor.
     *
     * @param array $paymentData
     */
    public function __construct(array $paymentData = [])
    {
        $fields = [
            'id',
            'payment_type',
            'date_from',
            'date_until',
            'purchase_quantity_from',
            'purchase_quantity_until',
            'purchase_value_from',
            'purchase_value_until',
            'priority',
            'areas',
            'articles',
            'categories',
            'user_groups',
            'title',
            'workingTitle',
            'description',
            'paymentType'
        ];

        foreach ($fields as $key) {
            if (isset($paymentData[$key])) {
                $this->attributes[$key] = $paymentData[$key];
            }
        }
    }

    /**
     * Return the payment title
     *
     * @param null|QUI\Locale $Locale
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (!isset($this->attributes['title'])) {
            return '';
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $current = $Locale->getCurrent();

        if (isset($this->attributes['title'][$current])) {
            return $this->attributes['title'][$current];
        }

        return reset($this->attributes['title']);
    }

    /**
     * Return the payment description
     *
     * @param null|QUI\Locale $Locale
     * @return mixed|string
     */
    public function getDescription(QUI\Locale $Locale = null): mixed
    {
        if (!isset($this->attributes['description'])) {
            return '';
        }

        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        $current = $Locale->getCurrent();

        if (isset($this->attributes['description'][$current])) {
            return $this->attributes['description'][$current];
        }

        return reset($this->attributes['description']);
    }

    /**
     * Return the payment type
     *
     * @return mixed|string
     */
    public function getPaymentType(): mixed
    {
        if (!isset($this->attributes['payment_type'])) {
            return '';
        }

        return $this->attributes['payment_type'];
    }

    /**
     * @return QUI\ERP\Accounting\Payments\Types\Payment|null
     */
    protected function getPayment(): ?QUI\ERP\Accounting\Payments\Types\Payment
    {
        if (!isset($this->attributes['id'])) {
            return null;
        }

        try {
            return QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment(
                $this->attributes['id']
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
        }

        return null;
    }

    /**
     * @param Invoice|InvoiceTemporary|InvoiceView $Invoice
     * @return string
     */
    public function getInvoiceInformationText(Invoice|InvoiceTemporary|InvoiceView $Invoice): string
    {
        if ($Invoice instanceof InvoiceView) {
            $Invoice = $Invoice->getInvoice();
        }

        if ($this->getPayment() === null) {
            return '';
        }

        $invoiceInformationText = $Invoice->getCustomDataEntry('InvoiceInformationText');

        if ($invoiceInformationText !== null) {
            return $invoiceInformationText;
        }

        try {
            return $this->getPayment()->getPaymentType()->getInvoiceInformationText($Invoice);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return '';
    }
}
