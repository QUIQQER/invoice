<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

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

$View = $Invoice->getView();

if (isset($_REQUEST['template'])) {
    try {
        QUI::getPackage($_REQUEST['template']);

        $View->setAttribute('template', $_REQUEST['template']);
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::writeDebugException($Exception);
    }
}

try {
    $HtmlPdfDocument = $View->toPDF();
    $HtmlPdfDocument->download();
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}
