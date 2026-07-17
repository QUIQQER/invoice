<?php

namespace QUITests\ERP\Accounting\Invoice\ProcessingStatus;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Status;
use ReflectionClass;
use ReflectionProperty;

class StatusUnitTest extends TestCase
{
    public function testGettersOptionsAndArrayRepresentation(): void
    {
        $Status = (new ReflectionClass(Status::class))->newInstanceWithoutConstructor();
        $this->setProperty($Status, 'id', 7);
        $this->setProperty($Status, 'color', '#123456');
        $this->setProperty($Status, 'options', [
            Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING => false
        ]);

        self::assertSame(7, $Status->getId());
        self::assertSame('#123456', $Status->getColor());
        self::assertFalse($Status->getOption(Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING));
        self::assertNull($Status->getOption('unknown'));

        $Status->setOption(Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING, true);
        $Status->setOption('unknown', true);

        self::assertTrue($Status->getOption(Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING));
        self::assertArrayNotHasKey('unknown', $Status->getOptions());

        $data = $Status->toArray(QUI::getLocale());

        self::assertSame(7, $data['id']);
        self::assertSame('#123456', $data['color']);
        self::assertTrue($data['options'][Handler::STATUS_OPTION_PREVENT_INVOICE_POSTING]);
        self::assertIsString($data['title']);

        $translatedData = $Status->toArray();

        self::assertIsArray($translatedData['title']);

        foreach (QUI::availableLanguages() as $language) {
            self::assertArrayHasKey($language, $translatedData['title']);
        }
    }

    private function setProperty(object $object, string $name, mixed $value): void
    {
        $Property = new ReflectionProperty($object, $name);
        $Property->setValue($object, $value);
    }
}
