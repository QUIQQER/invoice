<?php

/**
 * This file contains the PDF download for an invoice or a temporary invoice
 * It opens the native download dialog
 */

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

use QUI\Utils\Security\Orthos;

$Request  = QUI::getRequest();
$Invoices = QUI\ERP\Accounting\Invoice\Handler::getInstance();

$invoiceId = $Request->query->get('invoiceId');
$quiId     = Orthos::clear($Request->query->get('oid'));

try {
    $Invoice = QUI\ERP\Accounting\Invoice\Utils\Invoice::getInvoiceByString($invoiceId);
} catch (\Exception $Exception) {
    QUI\System\Log::writeException($Exception);

    $message = $Exception->getMessage();
    $message = QUI\Utils\Security\Orthos::clear($message);

    echo '
    <script>
    var invoiceId = search["invoiceId"];
    var objectId = search["oid"];
    var parent = window.parent;
    
    if (typeof parent.require !== "undefined") {
        parent.require(["qui/QUI"], function(QUI) {
            QUI.getMessageHandler().then(function(MH) {
                MH.addError("'.$message.'");
            });
            
            var Control = QUI.Controls.getById(objectId);
            
            if (Control) {
                Control.Loader.hide();     
            }
        });
    }
    </script>';
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

try {
    $HtmlPdfDocument = $View->toPDF();
    $HtmlPdfDocument->download();
} catch (QUI\Exception $Exception) {
    QUI\System\Log::writeException($Exception);
    exit;
}
