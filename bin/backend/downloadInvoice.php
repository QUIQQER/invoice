<?php

define('QUIQQER_SYSTEM', true);

require dirname(__FILE__, 5) . '/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request  = QUI::getRequest();
$Invoices = \QUI\ERP\Accounting\Invoice\Handler::getInstance();

$invoiceId = $Request->query->get('invoiceId');
$Invoice   = $Invoices->getInvoice($invoiceId);

$HtmlPdfDocument = $Invoice->getView()->toPDF();
$HtmlPdfDocument->download();
