<?php

use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use horstoeko\zugferd\ZugferdProfiles;
use QUI\ERP\Defaults;
use QUI\System\Log;
use Symfony\Component\HttpFoundation\Response;

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 5) . '/header.php';

$User = QUI::getUserBySession();

$jsClose = '
<script>
console.log(1);
    if (window.parent) {
        console.log(2);
        window.parent.require(["qui/QUI"], function(QUI) {
            console.log("fire");
            QUI.fireEvent("quiqqerInvoiceDownloadDone");
        });
    }
</script>';

if (!$User->canUseBackend()) {
    echo $jsClose;
    exit;
}

if (empty($_REQUEST['invoice'])) {
    echo $jsClose;
    exit;
}

if (empty($_REQUEST['type'])) {
    echo $jsClose;
    exit;
}

$invoiceHash = $_REQUEST['invoice'];
$type = mb_strtoupper($_REQUEST['type']);

$profileMap = [
    'PROFILE_BASIC' => ZugferdProfiles::PROFILE_BASIC,
    'PROFILE_BASICWL' => ZugferdProfiles::PROFILE_BASICWL,
    'PROFILE_EN16931' => ZugferdProfiles::PROFILE_EN16931,
    'PROFILE_EXTENDED' => ZugferdProfiles::PROFILE_EXTENDED,
    'PROFILE_XRECHNUNG' => ZugferdProfiles::PROFILE_XRECHNUNG,
    'PROFILE_XRECHNUNG_2' => ZugferdProfiles::PROFILE_XRECHNUNG_2,
    'PROFILE_XRECHNUNG_2_1' => ZugferdProfiles::PROFILE_XRECHNUNG_2_1,
    'PROFILE_XRECHNUNG_2_2' => ZugferdProfiles::PROFILE_XRECHNUNG_2_2,
    'PROFILE_MINIMUM' => ZugferdProfiles::PROFILE_MINIMUM,
    'PROFILE_XRECHNUNG_2_3' => ZugferdProfiles::PROFILE_XRECHNUNG_2_3,
    'PROFILE_XRECHNUNG_3' => ZugferdProfiles::PROFILE_XRECHNUNG_3,
];

try {
    $Invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);
    $fileName = InvoiceUtils::getInvoiceFilename($Invoice);

    if ($type === 'PDF') {
        $defaultTemplates = Defaults::conf('output', 'default_templates');
        $defaultTemplates = json_decode($defaultTemplates, true);

        $templateProvider = '';
        $templateId = '';

        if (!empty($defaultTemplates['Invoice'])) {
            $templateProvider = $defaultTemplates['Invoice']['provider'];
            $templateId = $defaultTemplates['Invoice']['id'];
        }

        $Request = QUI::getRequest();

        $Request->query->set('id', $invoiceHash);
        $Request->query->set('t', 'Invoice');
        $Request->query->set('ep', 'quiqqer/invoice');
        $Request->query->set('tpl', $templateId);
        $Request->query->set('tplpr', $templateProvider);

        include dirname(__FILE__, 4) . '/erp/bin/output/backend/download.php';
    } else {
        $document = InvoiceUtils::getElectronicInvoice($Invoice, $profileMap[$type]);
        $content = $document->getContent();
        $contentType = 'application/xml';
        $fileName .= '.xml';

        $response = new Response($content);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->headers->set('Content-Length', strlen($content));
        $response->send();
    }
} catch (Exception $e) {
    Log::writeException($e);
}

echo $jsClose;
exit;
