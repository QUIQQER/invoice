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
} catch (QUI\Exception $Exception) {
    try {
        $Invoice = $Invoices->getInvoice($invoiceId);
    } catch (QUI\Exception $Exception) {
        try {
            $Invoice = $Invoices->getTemporaryInvoice($invoiceId);
        } catch (QUI\Exception $Exception) {
            QUI\System\Log::writeDebugException($Exception);
            exit;
        }
    }
}

try {
    $HtmlPdfDocument = $Invoice->getView()->toPDF();
    $HtmlPdfDocument->download();
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}
