<?php

namespace QUITests\ERP\Accounting\Invoice;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaUnitTest extends TestCase
{
    public function testDatabaseSchemaUsesPortableDoctrineTypes(): void
    {
        $Document = new DOMDocument();
        self::assertTrue($Document->load(dirname(__DIR__, 5) . '/database.xml'));

        $XPath = new DOMXPath($Document);
        $allowedTypes = [
            'bigint',
            'boolean',
            'date',
            'datetime',
            'decimal',
            'float',
            'integer',
            'json',
            'smallint',
            'string',
            'text',
            'timestamp'
        ];

        $fields = $XPath->query('//field');
        self::assertNotFalse($fields);
        self::assertGreaterThan(0, $fields->count());

        foreach ($fields as $Field) {
            self::assertInstanceOf(DOMElement::class, $Field);
            self::assertContains(
                $Field->getAttribute('type'),
                $allowedTypes,
                'Non-portable database type for field "' . trim($Field->textContent) . '"'
            );
        }

        self::assertSame(0, $XPath->query('//primary | //auto_increment')?->count());
    }

    public function testPrimaryKeysAndAutoincrementAreDeclaredAsFieldMetadata(): void
    {
        $Document = new DOMDocument();
        self::assertTrue($Document->load(dirname(__DIR__, 5) . '/database.xml'));

        $XPath = new DOMXPath($Document);
        $tables = $XPath->query('//table');
        self::assertNotFalse($tables);

        foreach ($tables as $Table) {
            self::assertInstanceOf(DOMElement::class, $Table);
            $idFields = $XPath->query('./field[text()="id"]', $Table);

            self::assertNotFalse($idFields);
            self::assertSame(1, $idFields->count());

            $IdField = $idFields->item(0);
            self::assertInstanceOf(DOMElement::class, $IdField);
            self::assertSame('true', $IdField->getAttribute('primary'));
            self::assertSame('true', $IdField->getAttribute('autoincrement'));
        }
    }
}
