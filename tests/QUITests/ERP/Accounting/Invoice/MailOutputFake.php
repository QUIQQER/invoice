<?php

namespace QUITests\ERP\Accounting\Invoice;

class MailOutputFake
{
    public static array $calls = [];

    public static function sendPdfViaMail(...$arguments): void
    {
        self::$calls[] = $arguments;
    }

    public static function getDocumentPdf(string $entityId, string $entityType): \QUI\HtmlToPdf\Document
    {
        self::$calls[] = [$entityId, $entityType];

        return new \QUI\HtmlToPdf\Document();
    }
}
