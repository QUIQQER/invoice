<?php

namespace QUITests\ERP\Accounting\Invoice\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunClassInSeparateProcess;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use QUI;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Factory as ProcessingStatusFactory;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatusHandler;
use QUI\ERP\Accounting\Invoice\RestApi\Provider;
use QUI\ERP\Accounting\Payments\Methods\Standard\Payment as StandardPayment;
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
    private ?string $postedGlobalProcessId = null;
    private ?string $customerUuid = null;
    private ?int $processingStatusId = null;

    /** @var list<string> */
    private array $temporaryInvoiceIds = [];

    /** @var list<int> */
    private array $paymentIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryInvoiceIds as $temporaryInvoiceId) {
            QUI::getDataBaseConnection()->delete(
                Handler::getInstance()->temporaryInvoiceTable(),
                ['id' => $temporaryInvoiceId]
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

        foreach ($this->paymentIds as $paymentId) {
            QUI::getDataBaseConnection()->delete(QUI::getDBTableName('payments'), ['id' => $paymentId]);
        }

        if ($this->processingStatusId !== null) {
            try {
                ProcessingStatusHandler::getInstance()->deleteProcessingStatus($this->processingStatusId);
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
        $this->temporaryInvoiceIds[] = (string)$Draft->getId();

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

    public function testCreateInvoiceUsesCustomerDefaultPaymentBeforeRequestedPayment(): void
    {
        [$User, $Address, $customerNumber] = $this->createCustomer('default-payment');
        $paymentId = 42001;
        QUI::getDataBaseConnection()->insert(QUI::getDBTableName('payments'), [
            'id' => $paymentId,
            'active' => 1,
            'payment_type' => StandardPayment::class,
            'icon' => '',
            'priority' => 1
        ]);
        $this->paymentIds[] = $paymentId;

        $User->setAttribute('quiqqer.erp.standard.payment', $paymentId);
        $User->save(QUI::getUsers()->getSystemUser());

        $Draft = $this->createDraft([
            'customer_no' => $customerNumber,
            'invoice_address_id' => $Address->getUUID(),
            'payment_method' => -1
        ]);

        self::assertSame($User->getUUID(), $Draft->getCustomer()?->getUUID());
        self::assertSame($paymentId, (int)$Draft->getAttribute('payment_method'));
        self::assertSame(StandardPayment::class, $Draft->getPayment()->getPaymentType());
    }

    public function testCreateInvoiceFallsBackForInvalidCustomerAndAddressData(): void
    {
        [$User, , $customerNumber] = $this->createCustomer('invalid-data');
        $defaultAddressUuid = $User->getStandardAddress()->getUUID();

        $DraftWithoutCustomer = $this->createDraft([
            'customer_no' => 'missing-customer',
            'invoice_address_id' => 'missing-address',
            'payment_method' => -1
        ]);

        self::assertNull($DraftWithoutCustomer->getCustomer());
        self::assertFalse($DraftWithoutCustomer->getAttribute('customer_id'));
        self::assertSame('', $DraftWithoutCustomer->getAttribute('invoice_address_id'));

        $DraftWithDefaultAddress = $this->createDraft([
            'customer_no' => $customerNumber,
            'invoice_address_id' => 'missing-address',
            'payment_method' => -1
        ]);

        self::assertSame($User->getUUID(), $DraftWithDefaultAddress->getCustomer()?->getUUID());
        self::assertSame($defaultAddressUuid, $DraftWithDefaultAddress->getAttribute('invoice_address_id'));
    }

    public function testCreateInvoicePersistsValidProcessingStatus(): void
    {
        $this->processingStatusId = ProcessingStatusFactory::getInstance()->getNextId() + 2000;
        ProcessingStatusFactory::getInstance()->createProcessingStatus(
            $this->processingStatusId,
            '#224466',
            ['de' => 'REST processing status'],
            [ProcessingStatusHandler::STATUS_OPTION_PREVENT_INVOICE_POSTING => false]
        );

        $Draft = $this->createDraft([
            'processing_status' => $this->processingStatusId,
            'payment_method' => -1
        ]);

        self::assertSame($this->processingStatusId, (int)$Draft->getAttribute('processing_status'));

        $storedStatus = QUI::getDataBaseConnection()->createQueryBuilder()
            ->select('processing_status')
            ->from(Handler::getInstance()->temporaryInvoiceTable())
            ->where('id = :id')
            ->setParameter('id', $Draft->getId())
            ->executeQuery()
            ->fetchOne();

        self::assertSame($this->processingStatusId, (int)$storedStatus);
    }

    /**
     * @param array<string, mixed> $invoiceData
     */
    private function createDraft(array $invoiceData): InvoiceTemporary
    {
        $invoiceData += [
            'source' => 'phpunit-rest-special-case',
            'articles' => [[
                'title' => 'REST special case article',
                'articleNo' => 'REST-SPECIAL-1',
                'unitPrice' => 10,
                'quantity' => 1,
                'vat' => 19
            ]]
        ];

        $Response = (new Provider())->createInvoice(
            $this->request(['invoiceData' => $invoiceData]),
            new Response(),
            []
        );

        self::assertSame(
            200,
            $Response->getStatusCode(),
            json_encode($this->body($Response), JSON_THROW_ON_ERROR)
        );
        self::assertFalse($this->body($Response)['error']);

        $Draft = QUI\ERP\Accounting\Invoice\Utils\Invoice::getTemporaryInvoiceByString(
            $this->body($Response)['msg']['invoice_id']
        );
        $this->temporaryInvoiceIds[] = (string)$Draft->getId();

        return $Draft;
    }

    /**
     * @return array{0: UserInterface, 1: QUI\Users\Address, 2: string}
     */
    private function createCustomer(string $suffix): array
    {
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();
        $customerNumber = (string)random_int(800000, 899999);
        $username = 'invoice-rest-' . $suffix . '-' . uniqid();
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
            'street_no' => 'API-Straße 8',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
            'mail' => $username . '@example.invalid'
        ], $SystemUser);

        return [$User, $Address, $customerNumber];
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
