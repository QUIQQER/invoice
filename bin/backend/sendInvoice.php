<?php

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/header.php';

use QUI\Utils\Security\Orthos;

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

$Request  = QUI::getRequest();
$Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

$invoiceId = Orthos::clear($Request->query->get('invoiceId'));
$recipient = Orthos::clear($Request->query->get('recipient'));

echo '
<script>
    var i, len, parts;
        
    var search = {};
    var sData = window.location.search.replace("?", "").split("&");
    
    for (i = 0, len = sData.length; i <len; i++) {
        parts = sData[i].split("=");
        search[parts[0]] = parts[1];            
    }
        
    var invoiceId = search["invoiceId"];
    var objectId  = search["oid"];
    var parent    = window.parent;
    var Control   = parent.QUI.Controls.getById(objectId);
</script>
';

$sendErrorMessage = function ($message) {
    $message = Orthos::clear($message);

    echo '
    <script>
        if (Control) {
            Control.Loader.hide();
        }

        parent.require(["qui/QUI"], function(QUI) { 
            QUI.getMessageHandler().then(function(MH) {
                MH.addError("'.$message.'");
            });
        });
        
    </script>
';
    exit;
};

$sendSuccessMessage = function ($message) {
    $message = Orthos::clear($message);

    echo '
    <script>
        if (Control) {
            Control.close();
        }
        
        parent.require(["qui/QUI"], function(QUI) { 
            QUI.getMessageHandler().then(function(MH) {
                MH.addSuccess("'.$message.'");
            });
        });
    </script>
';
    exit;
};


try {
    $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
} catch (QUI\Exception $Exception) {
    $sendErrorMessage($Exception->getMessage());
}

if (empty($recipient)) {
    $sendErrorMessage(
        QUI::getLocale()->get('quiqqer/invoice', 'message.invoice.send.empty.recipient')
    );
}

try {
    $Invoice->sendTo($recipient);

    $sendSuccessMessage(
        QUI::getLocale()->get('quiqqer/invoice', 'message.invoice.send.successfully', [
            'ID'        => $Invoice->getId(),
            'recipient' => $recipient
        ])
    );
} catch (QUI\Exception $Exception) {
    $sendErrorMessage($Exception->getMessage());
}
