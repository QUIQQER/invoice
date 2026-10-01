<?php

namespace QUI\ERP\Order;

use QUI;
use QUI\ERP\Accounting\Invoice\Handler as InvoiceHandler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Payments\Transactions\Handler as TransactionHandler;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;

if (!class_exists(Order::class)) {
    class Order extends AbstractOrder
    {
        /** @param array<string, mixed> $data */
        public function __construct(private array $data = [])
        {
        }

        public function getAttribute(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }

        public function getUUID(): string
        {
            return $this->data['hash'];
        }

        public function getPrefixedNumber(): string
        {
            return (string)($this->data['id_str'] ?? '');
        }

        public function getInvoice(): Invoice
        {
            return InvoiceHandler::getInstance()->getInvoiceByHash($this->data['invoice_id']);
        }

        public function isPaid(): bool
        {
            return (int)$this->data['paid_status'] === QUI\ERP\Constants::PAYMENT_STATUS_PAID;
        }

        public function getPaidStatusInformation(): array
        {
            return [
                'paidDate' => null,
                'paidData' => []
            ];
        }

        public function addTransaction(Transaction $Transaction): void
        {
            $this->getInvoice()->linkTransaction($Transaction);
            $this->calculatePayments();
        }

        public function calculatePayments(): void
        {
            $paid = 0.0;

            foreach (TransactionHandler::getInstance()->getTransactionsByHash($this->getUUID()) as $Transaction) {
                if ($Transaction->getStatus() === TransactionHandler::STATUS_COMPLETE) {
                    $paid += $Transaction->getAmount();
                }
            }

            $status = QUI\ERP\Constants::PAYMENT_STATUS_OPEN;

            if ($paid > 0) {
                $status = $paid >= (float)$this->getInvoice()->getAttribute('sum')
                    ? QUI\ERP\Constants::PAYMENT_STATUS_PAID
                    : QUI\ERP\Constants::PAYMENT_STATUS_PART;
            }

            // Recalculate only the order: invoice status propagation is the behavior under test.
            $this->data['paid_status'] = $status;
            QUI::getDataBaseConnection()->update(
                Handler::getInstance()->table(),
                ['paid_status' => $status],
                ['hash' => $this->getUUID()]
            );
        }
    }
}
