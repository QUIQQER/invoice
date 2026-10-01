<?php

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;

if (!class_exists(EventHandling::class)) {
    class EventHandling
    {
        public static function onTransactionCreate(Transaction $Transaction): void
        {
            try {
                $Order = Handler::getInstance()->getOrderByGlobalProcessId($Transaction->getGlobalProcessId());
            } catch (QUI\Exception) {
                return;
            }

            $Order->addTransaction($Transaction);
        }

        public static function onTransactionStatusChange(Transaction $Transaction): void
        {
            try {
                $Order = Handler::getInstance()->getOrderByGlobalProcessId($Transaction->getGlobalProcessId());
            } catch (QUI\Exception) {
                return;
            }

            $Order->calculatePayments();
        }
    }
}
