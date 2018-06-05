<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request   = QUI::getRequest();
$Invoices  = QUI\ERP\Accounting\Invoice\Handler::getInstance();
$invoiceId = $Request->query->get('invoiceId');

try {
    $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
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

$HtmlPdfDocument = $View->toPDF();
$imageFile       = $HtmlPdfDocument->createImage();

$Response = QUI::getGlobalResponse();
$Response->headers->set('Content-Type', 'image/jpg');
$Response->setContent(file_get_contents($imageFile));
$Response->send();

if (file_exists($imageFile)) {
    unlink($imageFile);
}
