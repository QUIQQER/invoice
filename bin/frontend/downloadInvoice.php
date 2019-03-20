<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/header.php';

$User     = QUI::getUserBySession();
$Request  = QUI::getRequest();
$Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

if (!QUI::getUsers()->isAuth($User) || QUI::getUsers()->isNobodyUser($User)) {
    exit;
}

$hash    = $Request->query->get('hash');
$Invoice = null;

try {
    $Invoice = $Invoices->getInvoiceByHash($hash);
} catch (QUI\Exception $Exception) {
    try {
        $Invoice = $Invoices->getInvoice($hash);
    } catch (QUI\Exception $Exception) {
        QUI\System\Log::writeDebugException($Exception);
    }
}

if ($Invoice === null) {
    exit;
}

try {
    if ($User->getId() !== $Invoice->getCustomer()->getId()) {
        exit;
    }

    $View = $Invoice->getView();

    $HtmlPdfDocument = $View->toPDF();
    $HtmlPdfDocument->download();
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}
