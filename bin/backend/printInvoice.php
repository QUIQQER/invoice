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

$streamFile = URL_OPT_DIR.'quiqqer/invoice/bin/backend/printStreamInvoice.php?invoiceId='.$Invoice->getId();

echo '
<html>
<head>
    <style>
        body, html {
            margin: 0 !important;
            padding: 0 !important;
        }
 
        @page {
            margin: 0 !important;
        }
        
        .container {
            padding: 2.5em;
            width: calc(100% - 5em);
        }
    </style>
</head>
<body>
    <div class="container">
        <img 
            id="pdfDocument" 
            src="'.$streamFile.'"  
            style="max-width: 100%;"
           
        />
    </div>
    <script>
        var i, len, parts;
        
        var search = {};
        var sData = window.location.search.replace("?", "").split("&");
        
        for (i = 0, len = sData.length; i <len; i++) {
            parts = sData[i].split("=");
            search[parts[0]] = parts[1];            
        }
        
        var invoiceId = search["invoiceId"];
        var objectId = search["oid"];
        var parent = window.parent;
        
        window.onload = function() {
            window.print();
            parent.QUI.Controls.getById(objectId).$onPrintFinish(invoiceId);
        }
    </script>
</body>
</html>
<!--<script>window.print()</script>-->';
