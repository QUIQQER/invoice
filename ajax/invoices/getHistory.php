<?php

/**
 * This file contains package_quiqqer_invoice_ajax_invoices_getHistory
 */

/**
 * Returns the combined invoice history
 *
 * @param string $invoiceId - ID of the invoice or invoice hash
 *
 * @return array
 */
QUI::$Ajax->registerFunction(
    'package_quiqqer_invoice_ajax_invoices_getHistory',
    function ($invoiceId) {
        $Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

        try {
            $Invoice = $Invoices->get($invoiceId);
        } catch (QUI\Exception $Exception) {
            try {
                $Invoice = $Invoices->getInvoiceByHash($invoiceId);
            } catch (QUI\Exception $Exception) {
                $Invoice = $Invoices->getTemporaryInvoiceByHash($invoiceId);
            }
        }

        /* @var $Invoice \QUI\ERP\Accounting\Invoice\Invoice */
        QUI\ERP\Accounting\Calc::calculatePayments($Invoice);

        $History = $Invoice->getHistory();
        $history = \array_map(function ($history) {
            $history['type'] = 'history';

            return $history;
        }, $History->toArray());

        $Comments = $Invoice->getComments();
        $comments = \array_map(function ($comment) {
            $comment['type'] = 'comment';

            return $comment;
        }, $Comments->toArray());

        $history = \array_merge($history, $comments);

        // transactions
        $Transactions = QUI\ERP\Accounting\Payments\Transactions\Handler::getInstance();
        $transactions = $Transactions->getTransactionsByHash($Invoice->getHash());

        foreach ($transactions as $Tx) {
            /* @var $Tx \QUI\ERP\Accounting\Payments\Transactions\Transaction */
            $history[] = [
                'message' => $Tx->parseToText(),
                'time'    => \strtotime($Tx->getDate()),
                'type'    => 'transaction',
            ];
        }

        // sort
        \usort($history, function ($a, $b) {
            if ($a['time'] == $b['time']) {
                return 0;
            }

            return ($a['time'] > $b['time']) ? -1 : 1;
        });

        return $history;
    },
    ['invoiceId'],
    'Permission::checkAdminUser'
);
