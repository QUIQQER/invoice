<?php

namespace QUITests\ERP\Accounting\Invoice;

class MailOutputFake
{
    public static array $calls = [];

    public static function sendPdfViaMail(...$arguments): void
    {
        self::$calls[] = $arguments;
    }
}
