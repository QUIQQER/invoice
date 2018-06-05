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
    $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
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
    QUI::getLocale()->get('quiqqer/invoice', 'message.invoice.send.successfully', [
        'ID'        => $Invoice->getId(),
        'recipient' => $recipient
    ])
);

echo '<script>
if (typeof window.parent.require !== "undefined") {
    window.parent.require(["QUIQQER"], function(QUIQQER) {
        QUIQQER.isAuthenticated();
    });    
}
</script>';
