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
$recipient = $Request->query->get('recipient');

try {
    $Invoice = $Invoices->get($invoiceId);
} catch (QUI\Exception $Exception) {
    try {
        $Invoice = $Invoices->getInvoice($invoiceId);
    } catch (QUI\Exception $Exception) {
        $Invoice = $Invoices->getTemporaryInvoice($invoiceId);
    }
}

if (empty($recipient)) {
    QUI::getMessagesHandler()->sendSuccess(
        $User,
        QUI::getLocale()->get('quiqqer/invoice', 'message.invoice.send.empty.recipient')
    );
    exit;
}

$Invoice->sendTo($recipient);

QUI::getMessagesHandler()->sendSuccess(
    $User,
    QUI::getLocale()->get('quiqqer/invoice', 'message.invoice.send.successfully')
);

echo '<script>
if (typeof window.parent.require !== "undefined") {
    window.parent.require(["QUIQQER"], function(QUIQQER) {
        QUIQQER.isAuthenticated();
    });    
}
</script>';
