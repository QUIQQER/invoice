<?php

namespace QUI\ERP\Accounting\Invoice\Output;

use Exception;
use QUI;
use QUI\Locale;

/**
 * Class OutputProviderCancelled
 *
 * Output provider for quiqqer/invoice:
 *
 * Outputs previews and PDF files for cancelled invoices
 */
class OutputProviderCancelled extends OutputProviderInvoice
{
    /**
     * Get output type
     *
     * The output type determines the type of templates/providers that are used
     * to output documents.
     *
     * @return string
     */
    public static function getEntityType(): string
    {
        return 'Canceled';
    }

    /**
     * Get title for the output entity
     *
     * @param Locale|null $Locale $Locale (optional) - If omitted use \QUI::getLocale()
     * @return string
     */
    public static function getEntityTypeTitle(Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'OutputProvider.entity.title.Canceled');
    }

    /**
     * Get e-mail subject when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailSubject(int|string $entityId): string
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();
        $mailVars = OutputProviderInvoice::getInvoiceLocaleVar($Invoice, $Customer);

        // Additional mail placeholders for cancelled invoice
        $cancelledInvoiceId = $Invoice->getData('originalIdPrefixed');

        if (empty($cancelledInvoiceId)) {
            $cancelledInvoiceId = $Invoice->getData('originalId');

            try {
                $CancelledInvoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->get(
                    $cancelledInvoiceId
                );

                $cancelledInvoiceId = $CancelledInvoice->getId();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $mailVars['cancelledInvoiceId'] = $cancelledInvoiceId;

        return $Invoice->getCustomer()->getLocale()->get(
            'quiqqer/invoice',
            'invoice.cancelled.send.mail.subject',
            $mailVars
        );
    }

    /**
     * Get e-mail body when document is sent via mail
     *
     * @param int|string $entityId
     * @return string
     *
     * @throws QUI\Exception
     */
    public static function getMailBody(int|string $entityId): string
    {
        $Invoice = self::getEntity($entityId);
        $Customer = $Invoice->getCustomer();

        // Additional mail placeholders for cancelled invoice
        $mailVars = OutputProviderInvoice::getInvoiceLocaleVar($Invoice, $Customer);
        $cancelledInvoiceId = $Invoice->getData('originalIdPrefixed');

        if (empty($cancelledInvoiceId)) {
            $cancelledInvoiceId = $Invoice->getData('originalId');

            try {
                $CancelledInvoice = QUI\ERP\Accounting\Invoice\Handler::getInstance()->get(
                    $cancelledInvoiceId
                );

                $cancelledInvoiceId = $CancelledInvoice->getId();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $mailVars['cancelledInvoiceId'] = $cancelledInvoiceId;

        return $Customer->getLocale()->get(
            'quiqqer/invoice',
            'invoice.cancelled.send.mail.message',
            $mailVars
        );
    }
}
