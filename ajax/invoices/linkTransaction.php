<?php

/**
 * Link an existing transaction to an invoice
 *
 * @param string $invoiceHash
 * @param string $txId
 *
 * @return void
 *
 * @throws QUI\Exception
 */

use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\Exception;
use QUI\Utils\Security\Orthos;

QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_linkTransaction',
    function ($invoiceHash, $txId) {
        $Invoices = InvoiceHandler::getInstance();
        $Invoice = $Invoices->getInvoiceByHash(Orthos::clear($invoiceHash));
        $Transaction = TransactionHandler::getInstance()->get(Orthos::clear($txId));

        if ($Transaction->isHashLinked($Invoice->getHash())) {
            throw new Exception([
                'quiqqer/invoice',
                'message.ajax.invoices.linkTransaction.error.tx_already_linked',
                [
                    'invoiceNo' => $Invoice->getHash(),
                    'txId' => $Transaction->getTxId()
                ]
            ]);
        }

        $Invoice->linkTransaction($Transaction);
    },
    [
        'invoiceHash',
        'txId'
    ],
    'Permission::checkAdminUser'
);
