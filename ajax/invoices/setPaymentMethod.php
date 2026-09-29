<?php

/**
 * Change the payment method of a posted invoice.
 */

use QUI\ERP\Accounting\Invoice\Invoice;

QUI::getAjax()->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_setPaymentMethod',
    function ($invoiceId, $paymentMethod, $reason) {
        try {
            QUI\Permissions\Permission::checkPermission(
                'quiqqer.invoice.changePaymentMethod',
                QUI::getUserBySession()
            );
            $invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);

            if (!($invoice instanceof Invoice)) {
                throw new QUI\Exception(
                    QUI::getLocale()->get('quiqqer/invoice', 'exception.invoice.changePaymentMethod.invalidStatus')
                );
            }

            $invoice->changePaymentMethod($paymentMethod, $reason, true);
        } catch (QUI\Exception $exception) {
            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.invoice.changePaymentMethod.failed', [
                    'message' => $exception->getMessage()
                ]),
                $exception->getCode()
            );
        } catch (Throwable $exception) {
            QUI\System\Log::writeException($exception);

            throw new QUI\Exception(
                QUI::getLocale()->get('quiqqer/invoice', 'exception.invoice.changePaymentMethod.unexpected'),
                500
            );
        }
    },
    ['invoiceId', 'paymentMethod', 'reason'],
    'Permission::checkAdminUser'
);
