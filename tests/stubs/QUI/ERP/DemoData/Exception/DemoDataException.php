<?php

namespace QUI\ERP\DemoData\Exception;

if (!class_exists(DemoDataException::class, false)) {
    class DemoDataException extends \RuntimeException
    {
    }
}
