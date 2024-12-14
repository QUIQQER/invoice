<?php

use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use horstoeko\zugferd\ZugferdProfiles;
use QUI\ERP\Output\Output;
use Symfony\Component\HttpFoundation\Response;

define('QUIQQER_SYSTEM', true);
define('QUIQQER_AJAX', true);

require_once dirname(__FILE__, 5) . '/header.php';

$User = QUI::getUserBySession();

if (!$User->canUseBackend()) {
    exit;
}

if (empty($_REQUEST['invoice'])) {
    exit;
}

if (empty($_REQUEST['type'])) {
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
    if ($type === 'PDF') {
        $OutputDocument = Output::getDocumentPdf(
            $invoiceHash,
            QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice::getEntityType()
        );

        $OutputDocument->download();
        exit;
    }

    $Invoice = Handler::getInstance()->getInvoiceByHash($invoiceHash);
    $document = InvoiceUtils::getElectronicInvoice($Invoice, $profileMap[$type]);
    $xmlContent = $document->getContent();
    $fileName = InvoiceUtils::getInvoiceFilename($Invoice);

    $response = new Response($xmlContent);
    $response->headers->set('Content-Type', 'application/xml');
    $response->headers->set('Content-Disposition', 'attachment; filename="'. $fileName . '.xml"');
    $response->headers->set('Content-Length', strlen($xmlContent));
    $response->send();
} catch (Exception $e) {
}
