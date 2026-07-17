<?php

namespace QUITests\ERP\Accounting\Invoice;

use PHPUnit\Framework\TestCase;
use QUI\ERP\Accounting\Invoice\Settings;
use ReflectionClass;
use ReflectionProperty;

class SettingsUnitTest extends TestCase
{
    public function testGetSetAndMailCreationFlag(): void
    {
        $Settings = $this->createSettingsWithoutConstructor();
        $this->setProperty($Settings, 'settings', []);

        self::assertFalse($Settings->get('invoice', 'unknown'));
        self::assertFalse($Settings->sendMailAtInvoiceCreation());

        $Settings->set('invoice', 'sendMailAtCreation', 1);
        $Settings->set('invoice', 'custom', 'value');

        self::assertTrue($Settings->sendMailAtInvoiceCreation());
        self::assertSame('value', $Settings->get('invoice', 'custom'));
    }

    public function testCachedPrefixesAreReturned(): void
    {
        $Settings = $this->createSettingsWithoutConstructor();
        $this->setProperty($Settings, 'invoicePrefix', 'INV-');
        $this->setProperty($Settings, 'temporaryInvoicePrefix', 'EDIT-');

        self::assertSame('INV-', $Settings->getInvoicePrefix());
        self::assertSame('EDIT-', $Settings->getTemporaryInvoicePrefix());
    }

    private function createSettingsWithoutConstructor(): Settings
    {
        return (new ReflectionClass(Settings::class))->newInstanceWithoutConstructor();
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
