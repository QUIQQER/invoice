<?php

namespace QUI\ERP\Accounting\Invoice;

use DateTime;
use QUI;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\ERP\Accounting\Payments\Transactions\IncomingPayments\PaymentReceiverInterface;
use QUI\ERP\Accounting\Payments\Types\PaymentInterface;
use QUI\ERP\Address;
use QUI\ERP\Currency\Currency;
use QUI\Exception;
use QUI\Locale;

use function date_create;

class PaymentReceiver implements PaymentReceiverInterface
{
    /**
     * @var null|InvoiceTemporary|Invoice $Invoice
     */
    protected null | InvoiceTemporary | Invoice $Invoice = null;

    /**
     * Get entity type descriptor
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Invoice';
    }

    /**
     * Get entity type title
     *
     * @param Locale|null $Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return string
     */
    public static function getTypeTitle(null | Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'PaymentReceiver.Invoice.title');
    }

    /**
     * PaymentReceiverInterface constructor.
     * @param string|int $id - Payment receiver entity ID
     * @throws Exception
     */
    public function __construct($id)
    {
        $this->Invoice = InvoiceUtils::getInvoiceByString($id);
        $this->Invoice->calculatePayments();
    }

    /**
     * Get payment address of the debtor (e.g. customer)
     *
     * @return Address|false
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function getDebtorAddress(): bool | Address
    {
        return $this->Invoice->getCustomer()->getStandardAddress();
    }

    /**
     * Get full document no
     *
     * @return string
     */
    public function getDocumentNo(): string
    {
        return $this->Invoice->getPrefixedNumber();
    }

    /**
     * Get the unique recipient no. of the debtor (e.g. customer no.)
     *
     * @return string
     * @throws Exception
     * @throws QUI\ERP\Exception
     */
    public function getDebtorNo(): string
    {
        return $this->Invoice->getCustomer()->getAttribute('customerNo');
    }

    /**
     * Get date of the document
     *
     * @return DateTime
     */
    public function getDate(): DateTime
    {
        $date = $this->Invoice->getAttribute('date');

        if (!empty($date)) {
            $Date = date_create($date);

            if ($Date) {
                return $Date;
            }
        }

        return date_create();
    }

    /**
     * Get entity due date (if applicable)
     *
     * @return DateTime|false
     */
    public function getDueDate(): DateTime | bool
    {
        $date = $this->Invoice->getAttribute('time_for_payment');

        if (!empty($date)) {
            $Date = date_create($date);

            if ($Date) {
                return $Date;
            }
        }

        return false;
    }

    /**
     * @return Currency
     * @throws Exception
     */
    public function getCurrency(): Currency
    {
        return $this->Invoice->getCurrency();
    }

    /**
     * Get the total amount of the document
     *
     * @return float
     */
    public function getAmountTotal(): float
    {
        return $this->Invoice->getAttribute('sum');
    }

    /**
     * Get the total amount still open of the document
     *
     * @return float
     */
    public function getAmountOpen(): float
    {
        return $this->Invoice->getAttribute('toPay');
    }

    /**
     * Get the total amount already paid of the document
     *
     * @return float
     */
    public function getAmountPaid(): float
    {
        return $this->Invoice->getAttribute('paid');
    }

    /**
     * Get payment method
     *
     * @return PaymentInterface|false
     */
    public function getPaymentMethod(): bool | PaymentInterface
    {
        try {
            return QUI\ERP\Accounting\Payments\Payments::getInstance()->getPayment(
                $this->Invoice->getAttribute('payment_method')
            );
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            return false;
        }
    }

    /**
     * Get the current payment status of the ERP object
     *
     * @return int - One of \QUI\ERP\Constants::PAYMENT_STATUS_*
     */
    public function getPaymentStatus(): int
    {
        return (int)$this->Invoice->getAttribute('paid_status');
    }
}
