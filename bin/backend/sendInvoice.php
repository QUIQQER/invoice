<?php

define('QUIQQER_SYSTEM', true);

require dirname(__FILE__, 5) . '/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request  = QUI::getRequest();
$Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

$invoiceId = $Request->query->get('invoiceId');
$recipient = $Request->query->get('recipient');

try {
    $Invoice = $Invoices->get($invoiceId);
} catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
    try {
        $Invoice = $Invoices->getInvoice($invoiceId);
    } catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
        $Invoice = $Invoices->getTemporaryInvoice($invoiceId);
    }
}

if (empty($recipient)) {
    exit;
}

$Invoice->sendTo($recipient);