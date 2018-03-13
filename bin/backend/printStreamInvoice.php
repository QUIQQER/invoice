<?php

define('QUIQQER_SYSTEM', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request  = QUI::getRequest();
$Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

$invoiceId = $Request->query->get('invoiceId');

try {
    $Invoice = $Invoices->get($invoiceId);
} catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
    try {
        $Invoice = $Invoices->getInvoice($invoiceId);
    } catch (QUI\ERP\Accounting\Invoice\Exception $Exception) {
        $Invoice = $Invoices->getTemporaryInvoice($invoiceId);
    }
}

$HtmlPdfDocument = $Invoice->getView()->toPDF();
$imageFile       = $HtmlPdfDocument->createImage();

$Response = QUI::getGlobalResponse();
$Response->headers->set('Content-Type', 'image/jpg');
$Response->setContent(file_get_contents($imageFile));
$Response->send();

unlink($imageFile);
