<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\RestApi\Provider;
use QUI\Interfaces\Users\User as UserInterface;
use QUI\REST\Response;
use QUITests\ERP\Accounting\Invoice\SqliteIntegrationTestCase;
use ReflectionMethod;
use Throwable;

#[PreserveGlobalState(false)]
#[RunClassInSeparateProcess]
class RestApiProviderTest extends SqliteIntegrationTestCase
{
    private ?UserInterface $previousSessionUser = null;
    private ?string $temporaryInvoiceId = null;
    private ?string $postedGlobalProcessId = null;
    private ?string $customerUuid = null;

    protected function setUp(): void
    {
        parent::setUp();

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

        if ($this->postedGlobalProcessId !== null) {
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->invoiceTable(),
                ['global_process_id' => $this->postedGlobalProcessId]
            );
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['global_process_id' => $this->postedGlobalProcessId]
            );
        }

        if ($this->customerUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->customerUuid);
            } catch (Throwable) {
            }
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }

        parent::tearDown();
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

    public function testCreateInvoiceCanPostForExistingCustomer(): void
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $customerNumber = (string)random_int(700000, 799999);
        $username = 'invoice-rest-customer-' . uniqid();
        $User = $Users->createChildWithAttributes([
            'username' => $username,
            'email' => $username . '@example.invalid',
            'firstname' => 'REST',
            'lastname' => 'Customer',
            'customerId' => $customerNumber
        ], $SystemUser);
        $this->customerUuid = $User->getUUID();
        $Address = $User->addAddress([
            'firstname' => 'REST',
            'lastname' => 'Customer',
            'street_no' => 'API-Straße 5',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        $Response = (new Provider())->createInvoice(
            $this->request([
                'invoiceData' => [
                    'source' => 'phpunit-post',
                    'customer_no' => $customerNumber,
                    'invoice_address_id' => $Address->getUUID(),
                    'articles' => [[
                        'title' => 'Posted REST article',
                        'articleNo' => 'REST-POST-1',
                        'unitPrice' => 20,
                        'quantity' => 1,
                        'vat' => 19
                    ]],
                    'payment_method' => -1,
                    'post' => true,
                    'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_PAID
                ]
            ]),
            new Response(),
            []
        );

        self::assertSame(
            200,
            $Response->getStatusCode(),
            json_encode($this->body($Response), JSON_THROW_ON_ERROR)
        );
        $Invoice = Handler::getInstance()->get($this->body($Response)['msg']['invoice_id']);
        $this->postedGlobalProcessId = $Invoice->getGlobalProcessId();

        self::assertSame($User->getUUID(), $Invoice->getCustomer()->getUUID());
        self::assertSame(QUI\ERP\Constants::PAYMENT_STATUS_PAID, $Invoice->getAttribute('paid_status'));
    }

    private function request(array $body): ServerRequestInterface
    {
        $Stream = $this->createStub(StreamInterface::class);
        $Stream->method('getContents')->willReturn('');

        $Request = $this->createStub(ServerRequestInterface::class);
        $Request->method('getQueryParams')->willReturn([]);
        $Request->method('getParsedBody')->willReturn($body);
        $Request->method('getBody')->willReturn($Stream);

        return $Request;
    }

    private function body(\Psr\Http\Message\MessageInterface $Response): array
    {
        return json_decode((string)$Response->getBody(), true);
    }
}
