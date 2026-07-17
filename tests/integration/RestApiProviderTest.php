<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use GuzzleHttp\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\RestApi\Provider;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\REST\Response;
use ReflectionMethod;
use ReflectionProperty;
use Throwable;

class RestApiProviderTest extends TestCase
{
    private ?UserInterface $previousSessionUser = null;
    private ?string $temporaryInvoiceId = null;

    protected function setUp(): void
    {
        try {
            QUI::getDataBaseConnection()->executeQuery('SELECT 1')->free();
        } catch (Throwable $Exception) {
            self::markTestSkipped('QUIQQER database is not available: ' . $Exception->getMessage());
        }

        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
    }

    protected function tearDown(): void
    {
        if ($this->temporaryInvoiceId !== null) {
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['id' => $this->temporaryInvoiceId]
            );
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }
    }

    public function testCreateInvoiceValidatesRequestData(): void
    {
        $Provider = new Provider();

        $missingData = $Provider->createInvoice($this->request([]), new Response(), []);
        self::assertSame(400, $missingData->getStatusCode());
        self::assertSame(Provider::ERROR_CODE_MISSING_PARAMETERS, $this->body($missingData)['errorCode']);

        $invalidData = $Provider->createInvoice(
            $this->request(['invoiceData' => 'not-an-array']),
            new Response(),
            []
        );
        self::assertSame(400, $invalidData->getStatusCode());
        self::assertSame(Provider::ERROR_CODE_PARAMETER_INVALID, $this->body($invalidData)['errorCode']);

        $missingSource = $Provider->createInvoice(
            $this->request(['invoiceData' => ['articles' => []]]),
            new Response(),
            []
        );
        self::assertSame(400, $missingSource->getStatusCode());

        $missingQuantity = $Provider->createInvoice(
            $this->request([
                'invoiceData' => [
                    'source' => 'phpunit',
                    'articles' => [['title' => 'Missing quantity']]
                ]
            ]),
            new Response(),
            []
        );
        self::assertSame(400, $missingQuantity->getStatusCode());
        self::assertStringContainsString('quantity', $this->body($missingQuantity)['msg']);
    }

    public function testCreateInvoiceCreatesDraftFromCustomArticle(): void
    {
        $Provider = new Provider();
        $Response = $Provider->createInvoice(
            $this->request([
                'invoiceData' => [
                    'source' => 'phpunit',
                    'articles' => [[
                        'title' => 'REST test article',
                        'articleNo' => 'REST-1',
                        'description' => 'Created through the REST provider test',
                        'unitPrice' => 10,
                        'quantity' => 1,
                        'vat' => 19
                    ]],
                    'payment_method' => -1,
                    'currency' => 'EUR',
                    'project_name' => 'REST provider test',
                    'comments' => ['First comment', 123],
                    'additional_invoice_text' => 'Additional REST text',
                    'service_period' => '2026-07-01 - 2026-07-31',
                    'unknown_field' => 'removed'
                ]
            ]),
            new Response(),
            []
        );

        self::assertSame(200, $Response->getStatusCode());
        $body = $this->body($Response);
        self::assertFalse($body['error']);

        $Draft = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString(
            $body['msg']['invoice_id']
        );
        $this->temporaryInvoiceId = (string)$Draft->getId();

        self::assertSame('REST provider test', $Draft->getAttribute('project_name'));
        self::assertSame(1, $Draft->getArticles()->count());
        self::assertSame('period', json_decode($Draft->getAttribute('service_period'), true)['type']);
        self::assertFalse($Draft->getComments()->isEmpty());
    }

    public function testProviderMetadataAndServerErrorResponse(): void
    {
        $Provider = new Provider();

        self::assertFalse($Provider->getOpenApiDefinitionFile());
        self::assertSame('EcoynInvoice', $Provider->getName());
        self::assertNotSame('', $Provider->getTitle());
        self::assertNotSame('', $Provider->getTitle(QUI::getLocale()));

        $Method = new ReflectionMethod($Provider, 'getServerErrorResponse');
        $Response = $Method->invoke($Provider, 'Server error test');

        self::assertSame(500, $Response->getStatusCode());
        self::assertSame(Provider::ERROR_CODE_SERVER_ERROR, $this->body($Response)['errorCode']);
    }

    private function request(array $body): ServerRequest
    {
        return (new ServerRequest('POST', '/invoice/create'))->withParsedBody($body);
    }

    private function body(\Psr\Http\Message\MessageInterface $Response): array
    {
        return json_decode((string)$Response->getBody(), true);
    }

    private function replaceSessionUser(UserInterface $User): ?UserInterface
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $Property->setAccessible(true);

        $PreviousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }
}
