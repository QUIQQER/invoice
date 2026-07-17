<?php

namespace QUITests\ERP\Accounting\Invoice;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\ERP\Accounting\Invoice\McpProvider;
use ReflectionMethod;

class McpProviderUnitTest extends TestCase
{
    protected function tearDown(): void
    {
        Server::setRequestUser(null);
    }

    public function testRegisterUsesLocalStubsWithoutMcpModule(): void
    {
        Server::setRequestUser(QUI::getUsers()->getSystemUser());

        $Builder = new Builder();
        (new McpProvider())->register($Builder);

        $tools = $Builder->getTools();

        self::assertSame([
            'invoice_get',
            'invoice_search',
            'invoice_temporary_create',
            'invoice_temporary_update',
            'invoice_temporary_post'
        ], array_keys($tools));

        foreach ($tools as $tool) {
            self::assertIsCallable($tool['callback']);
            self::assertNotSame('', $tool['description']);
            self::assertIsArray($tool['inputSchema']);
        }

        self::assertInstanceOf(
            CallToolResult::class,
            $tools['invoice_get']['callback']('invoice-that-does-not-exist')
        );

        $searchResult = $tools['invoice_search']['callback'](
            'invoice-search-without-results-' . uniqid(),
            0,
            -10,
            'invalid order',
            null,
            '',
            '',
            '',
            ''
        );

        self::assertIsArray(
            $searchResult,
            ToolHelper::getLastException() instanceof \Throwable
                ? ToolHelper::getLastException()->getMessage()
                : 'MCP search did not return an array.'
        );

        self::assertSame(0, $searchResult['offset']);
        self::assertSame(1, $searchResult['limit']);
        self::assertSame(0, $searchResult['count']);
        self::assertSame([], $searchResult['invoices']);
    }

    public function testRegisterWithoutPermissionAddsNoTools(): void
    {
        Server::setRequestUser(new QUI\Users\Nobody());

        $Builder = new Builder();
        (new McpProvider())->register($Builder);

        self::assertSame([], $Builder->getTools());
    }

    public function testPrivateValueParsersHandleSupportedInputs(): void
    {
        $Provider = new McpProvider();
        $parseDate = new ReflectionMethod($Provider, 'parseDateFilter');
        $decodeJson = new ReflectionMethod($Provider, 'decodeJson');

        self::assertSame(123, $parseDate->invoke($Provider, '123', true));
        self::assertSame('2026-07-17 00:00:00', $parseDate->invoke($Provider, '2026-07-17', true));
        self::assertSame('2026-07-17 23:59:59', $parseDate->invoke($Provider, '2026-07-17', false));
        self::assertSame('invalid date', $parseDate->invoke($Provider, 'invalid date', true));

        self::assertSame(['value' => 1], $decodeJson->invoke($Provider, '{"value":1}'));
        self::assertSame('invalid json', $decodeJson->invoke($Provider, 'invalid json'));
        self::assertSame('', $decodeJson->invoke($Provider, ''));
        self::assertSame(['already' => 'decoded'], $decodeJson->invoke($Provider, ['already' => 'decoded']));
    }
}
