<?php

namespace QUITests\ERP\Accounting\Invoice;

use horstoeko\zugferd\ZugferdDocumentPdfReader;
use horstoeko\zugferd\ZugferdProfileResolver;
use horstoeko\zugferd\ZugferdProfiles;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Cache\Manager as Cache;
use QUI\ERP\Accounting\Invoice\EventHandler;
use QUI\ERP\Accounting\Invoice\Handler;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Invoice\McpProvider;
use QUI\ERP\Accounting\Invoice\Settings;

class McpDownloadTest extends TestCase
{
    private InvoiceTemporary $Draft;
    private string $processId;
    private QUI\Interfaces\Users\User $PreviousRequestUser;

    /** @var array<string, mixed> */
    private array $previousInvoiceSettings;

    /** @var array<string, mixed> */
    private array $previousCompanySettings;

    /** @var list<string> */
    private array $cachePrefixes = [];

    protected function setUp(): void
    {
        $this->PreviousRequestUser = Server::getRequestUser();
        Server::setRequestUser(QUI::getUsers()->getSystemUser());
        $Config = Settings::getConfig();
        $this->previousInvoiceSettings = $Config->toArray()['invoice'] ?? [];
        $Config->setValue('invoice', 'zugferdInvoiceAttachment', 0);
        $Config->setValue('invoice', 'zugferdInvoiceAttachmentType', ZugferdProfiles::PROFILE_EN16931);
        $ErpConfig = QUI::getPackage('quiqqer/erp')->getConfig();
        $this->previousCompanySettings = $ErpConfig->toArray()['company'] ?? [];
        $ErpConfig->setValue('company', 'name', 'PHPUnit Seller');
        $this->processId = 'phpunit-invoice-mcp-pdf-' . bin2hex(random_bytes(8));
        $this->Draft = $this->createInvoice(true);
    }

    protected function tearDown(): void
    {
        foreach ($this->cachePrefixes as $prefix) {
            Cache::clear($prefix);
        }

        $Connection = QUI::getDataBaseConnection();
        $Connection->delete(Handler::getInstance()->temporaryInvoiceTable(), ['global_process_id' => $this->processId]);
        $Connection->delete(Handler::getInstance()->invoiceTable(), ['global_process_id' => $this->processId]);
        Settings::getConfig()->setSection('invoice', $this->previousInvoiceSettings);
        QUI::getPackage('quiqqer/erp')->getConfig()->setSection('company', $this->previousCompanySettings);
        Server::setRequestUser($this->PreviousRequestUser);
    }

    public function testDraftCanBeDownloadedByIdPrefixedNumberAndHash(): void
    {
        $content = "%PDF-1.4\nInvoice draft\x00\xff\n%%EOF\n";
        $download = $this->downloadCallback($content, 3);

        foreach ([$this->Draft->getId(), $this->Draft->getPrefixedNumber(), $this->Draft->getUUID()] as $id) {
            $result = $download((string)$id, true);

            $this->assertSuccessfulResult($result);
            self::assertSame($this->Draft->getId(), $result['invoiceId']);
            self::assertSame($this->Draft->getUUID(), $result['invoiceHash']);
            self::assertSame($this->Draft->getPrefixedNumber(), $result['prefixedNumber']);
            self::assertTrue($result['draft']);
            self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $result['downloadId']);
            self::assertSame('invoice.pdf', $result['download']['filename']);
            self::assertSame('application/pdf', $result['download']['mimeType']);
            self::assertSame('base64', $result['download']['encoding']);
            self::assertSame($content, base64_decode($result['download']['contentBase64'], true));
            self::assertSame(strlen($content), $result['download']['size']);
            self::assertSame(strlen($content), $result['download']['chunkSize']);
            self::assertSame(0, $result['download']['offset']);
            self::assertTrue($result['download']['complete']);
            self::assertNull($result['download']['nextOffset']);
        }
    }

    public function testPostedInvoiceCanBeDownloadedByIdPrefixedNumberAndHash(): void
    {
        $Invoice = $this->createInvoice(false);
        $Provider = $this->getMockBuilder(McpProvider::class)->onlyMethods(['createInvoicePdf'])->getMock();
        $Provider->expects(self::exactly(3))->method('createInvoicePdf')->willReturnCallback(
            static function (Invoice | InvoiceTemporary $LoadedInvoice) use ($Invoice): array {
                self::assertInstanceOf(Invoice::class, $LoadedInvoice);
                self::assertSame($Invoice->getUUID(), $LoadedInvoice->getUUID());

                return ['content' => '%PDF-posted', 'filename' => 'posted.pdf'];
            }
        );
        $download = $this->registerDownload($Provider);

        foreach ([$Invoice->getId(), $Invoice->getPrefixedNumber(), $Invoice->getUUID()] as $id) {
            $result = $download((string)$id);
            $this->assertSuccessfulResult($result);
            self::assertSame($Invoice->getId(), $result['invoiceId']);
            self::assertSame($Invoice->getUUID(), $result['invoiceHash']);
            self::assertFalse($result['draft']);
            self::assertSame('%PDF-posted', base64_decode($result['download']['contentBase64'], true));
        }
    }

    public function testChunksAndRetriesKeepOriginalBytesAfterDraftChanges(): void
    {
        $content = "%PDF-1.4\nOriginal invoice\x00\xff\n%%EOF\n";
        $download = $this->downloadCallback($content);
        $first = $download($this->Draft->getUUID(), true, 0, 8);
        $this->assertSuccessfulResult($first);
        self::assertFalse($first['download']['complete']);
        self::assertSame(8, $first['download']['nextOffset']);

        QUI::getDataBaseConnection()->update(
            Handler::getInstance()->temporaryInvoiceTable(),
            ['project_name' => 'Changed after rendering'],
            ['hash' => $this->Draft->getUUID()]
        );

        $second = $download($this->Draft->getPrefixedNumber(), true, 8, 5_242_880, $first['downloadId']);
        $retry = $download($this->Draft->getUUID(), true, 8, 5_242_880, $first['downloadId']);
        $this->assertSuccessfulResult($second);
        self::assertSame($second, $retry);
        self::assertSame($first['downloadId'], $second['downloadId']);
        self::assertSame(
            $content,
            base64_decode($first['download']['contentBase64'], true)
                . base64_decode($second['download']['contentBase64'], true)
        );
        self::assertTrue($second['download']['complete']);
        self::assertNull($second['download']['nextOffset']);

        $end = $download($this->Draft->getUUID(), true, strlen($content), 8, $first['downloadId']);
        $this->assertSuccessfulResult($end);
        self::assertSame('', $end['download']['contentBase64']);
        self::assertSame(0, $end['download']['chunkSize']);
        self::assertTrue($end['download']['complete']);

        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, strlen($content) + 1, 8, $first['downloadId'])
        );

        Cache::clear($this->cachePrefixes[0] . $first['downloadId']);
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 8, 8, $first['downloadId'])
        );
    }

    public function testContinuationAndUnknownInvoiceErrorsDoNotRender(): void
    {
        $download = $this->downloadCallback(null);

        self::assertInstanceOf(CallToolResult::class, $download($this->Draft->getUUID(), true, 1));
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 0, 8, '../../secret')
        );
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 0, 8, str_repeat('a', 64))
        );
        self::assertInstanceOf(CallToolResult::class, $download('missing-invoice-' . bin2hex(random_bytes(8))));
        self::assertInstanceOf(CallToolResult::class, $download($this->Draft->getUUID()));
    }

    public function testSnapshotCannotBeUsedForAnotherInvoiceDraftFlagOrUser(): void
    {
        $download = $this->downloadCallback('%PDF-original');
        $first = $download($this->Draft->getUUID(), true, 0, 1);
        $this->assertSuccessfulResult($first);
        $Other = $this->createInvoice(true);

        self::assertInstanceOf(
            CallToolResult::class,
            $download($Other->getUUID(), true, 1, 8, $first['downloadId'])
        );

        // A posted invoice may retain its original draft's UUID.
        $this->createInvoice(false, $this->Draft->getUUID());
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), false, 1, 8, $first['downloadId'])
        );

        $OtherUser = $this->createMock(QUI\Users\User::class);
        $OtherUser->method('isSU')->willReturn(true);
        $OtherUser->method('getUUID')->willReturn('phpunit-other-download-user');
        Server::setRequestUser($OtherUser);
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 1, 8, $first['downloadId'])
        );

        Server::setRequestUser(new QUI\Users\Nobody());
        self::assertInstanceOf(CallToolResult::class, $download($this->Draft->getUUID(), true));
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 1, 8, $first['downloadId'])
        );
    }

    public function testChunkSizeIsBoundedAndOffsetsAreNormalized(): void
    {
        $content = '%PDF-' . str_repeat('a', 5_242_880);
        $download = $this->downloadCallback($content);
        $first = $download($this->Draft->getUUID(), true, -10, 0);
        $this->assertSuccessfulResult($first);
        self::assertSame(0, $first['download']['offset']);
        self::assertSame(1, $first['download']['chunkSize']);

        $second = $download($this->Draft->getUUID(), true, 1, PHP_INT_MAX, $first['downloadId']);
        $this->assertSuccessfulResult($second);
        self::assertSame(5_242_880, $second['download']['chunkSize']);
        self::assertFalse($second['download']['complete']);
        self::assertSame(5_242_881, $second['download']['nextOffset']);
    }

    public function testPdfRenderingFailureIsReturnedAsToolError(): void
    {
        $Provider = $this->getMockBuilder(McpProvider::class)->onlyMethods(['createInvoicePdf'])->getMock();
        $Provider->expects(self::once())->method('createInvoicePdf')
            ->willThrowException(new QUI\Exception('Renderer unavailable'));
        $download = $this->registerDownload($Provider);

        self::assertInstanceOf(CallToolResult::class, $download($this->Draft->getUUID(), true));
        self::assertSame('Renderer unavailable', ToolHelper::getLastException()->getMessage());
    }

    public function testFormatSchemaAdvertisesChoicesAndConfiguredDefault(): void
    {
        $Builder = new Builder();
        (new McpProvider())->register($Builder);
        $schema = $Builder->getTools()['invoice_download']['inputSchema'];

        self::assertSame(['pdf', 'xrechnung', 'zugferd'], $schema['properties']['format']['enum']);
        self::assertSame('pdf', $schema['properties']['format']['default']);
        self::assertSame(['invoiceId'], $schema['required']);

        $download = $this->downloadCallback(null);
        self::assertInstanceOf(
            CallToolResult::class,
            $download($this->Draft->getUUID(), true, 0, 8, null, 'unsupported')
        );
    }

    public function testXRechnungXmlDownloadsWithoutPdfRenderingAndKeepsFormatAcrossChunks(): void
    {
        $Invoice = $this->createElectronicInvoice();
        $download = $this->downloadCallback(null);
        $first = $download($Invoice->getUUID(), false, 0, 80, null, 'xrechnung');
        $this->assertSuccessfulResult($first);
        self::assertSame('xrechnung', $first['format']);
        self::assertSame('application/xml', $first['download']['mimeType']);
        self::assertStringEndsWith('.xml', $first['download']['filename']);

        $second = $download($Invoice->getUUID(), false, 80, 5_242_880, $first['downloadId'], 'xrechnung');
        $this->assertSuccessfulResult($second);
        self::assertTrue($second['download']['complete']);
        $xml = base64_decode($first['download']['contentBase64'], true)
            . base64_decode($second['download']['contentBase64'], true);
        self::assertSame(ZugferdProfiles::PROFILE_XRECHNUNG_3, ZugferdProfileResolver::resolveProfileId($xml));
        self::assertStringContainsString($Invoice->getPrefixedNumber(), $xml);

        self::assertInstanceOf(
            CallToolResult::class,
            $download($Invoice->getUUID(), false, 80, 80, $first['downloadId'], 'pdf')
        );
        self::assertInstanceOf(
            CallToolResult::class,
            $download($Invoice->getUUID(), false, 80, 80, $first['downloadId'], 'zugferd')
        );
    }

    public function testZugferdDownloadContainsEn16931Xml(): void
    {
        $Invoice = $this->createElectronicInvoice();
        $Pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $Pdf->WriteHTML('<h1>PHPUnit invoice</h1>');
        $download = $this->downloadCallback($Pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN));
        $result = $download($Invoice->getUUID(), false, 0, 5_242_880, null, 'zugferd');
        $this->assertSuccessfulResult($result);
        self::assertSame('zugferd', $result['format']);
        self::assertSame('application/pdf', $result['download']['mimeType']);
        self::assertStringEndsWith('.pdf', $result['download']['filename']);
        $content = base64_decode($result['download']['contentBase64'], true);
        self::assertStringStartsWith('%PDF-', $content);
        $xml = ZugferdDocumentPdfReader::getXmlFromContent($content);
        self::assertSame(ZugferdProfiles::PROFILE_EN16931, ZugferdProfileResolver::resolveProfileId($xml));
        self::assertStringContainsString($Invoice->getPrefixedNumber(), $xml);
    }

    public function testDefaultUsesSettingAndFallsBackToZugferdWhileExplicitPdfWins(): void
    {
        $Invoice = $this->createElectronicInvoice();
        $Pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $Pdf->WriteHTML('<h1>PHPUnit invoice</h1>');
        $pdf = $Pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        $download = $this->downloadCallback($pdf, 4);
        $Config = Settings::getConfig();

        foreach ([0 => 'pdf', 1 => 'zugferd', 2 => 'zugferd'] as $setting => $expected) {
            if ($setting === 2) {
                $Config->del('invoice', 'zugferdInvoiceAttachment');
            } else {
                $Config->setValue('invoice', 'zugferdInvoiceAttachment', $setting);
            }

            $Builder = new Builder();
            (new McpProvider())->register($Builder);
            $schema = $Builder->getTools()['invoice_download']['inputSchema'];
            self::assertSame($expected, $schema['properties']['format']['default']);

            $result = $download($Invoice->getUUID());
            $this->assertSuccessfulResult($result);
            self::assertSame($expected, $result['format']);
            $content = base64_decode($result['download']['contentBase64'], true);

            if ($expected === 'zugferd') {
                self::assertNotSame('', ZugferdDocumentPdfReader::getXmlFromContent($content));
            } else {
                self::assertSame($pdf, $content);
            }
        }

        $Config->setValue('invoice', 'zugferdInvoiceAttachment', 1);
        $result = $download($Invoice->getUUID(), false, 0, 5_242_880, null, 'pdf');
        $this->assertSuccessfulResult($result);
        self::assertSame('pdf', $result['format']);
        self::assertSame($pdf, base64_decode($result['download']['contentBase64'], true));
    }

    public function testZugferdUsesConfiguredProfileAndFallsBackToEn16931(): void
    {
        $Invoice = $this->createElectronicInvoice();
        $Pdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
        $Pdf->WriteHTML('<h1>PHPUnit invoice</h1>');
        $download = $this->downloadCallback($Pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), 2);
        $Config = Settings::getConfig();

        foreach ([ZugferdProfiles::PROFILE_BASIC, null] as $profile) {
            if ($profile === null) {
                $Config->del('invoice', 'zugferdInvoiceAttachmentType');
            } else {
                $Config->setValue('invoice', 'zugferdInvoiceAttachmentType', $profile);
            }

            $result = $download($Invoice->getUUID(), false, 0, 5_242_880, null, 'zugferd');
            $this->assertSuccessfulResult($result);
            $xml = ZugferdDocumentPdfReader::getXmlFromContent(base64_decode($result['download']['contentBase64'], true));
            self::assertSame($profile ?? ZugferdProfiles::PROFILE_EN16931, ZugferdProfileResolver::resolveProfileId($xml));
        }
    }

    public function testDownloadCanSuppressAutomaticXmlEmbeddingWithoutChangingSettings(): void
    {
        $Invoice = $this->createElectronicInvoice();
        $Config = Settings::getConfig();
        $Config->setValue('invoice', 'zugferdInvoiceAttachment', 1);
        $Document = new QUI\HtmlToPdf\Document();
        $Document->setAttribute('Entity', $Invoice);
        $Document->setAttribute(EventHandler::PDF_SKIP_ELECTRONIC_INVOICE, true);
        $path = tempnam(sys_get_temp_dir(), 'invoice-mcp-pdf-');

        try {
            file_put_contents($path, '%PDF-not-rendered-in-this-test');
            EventHandler::onQuiqqerHtmlToPDFCreated($Document, $path);

            self::assertSame('%PDF-not-rendered-in-this-test', file_get_contents($path));
            self::assertEquals(1, $Config->getValue('invoice', 'zugferdInvoiceAttachment'));
        } finally {
            unlink($path);
        }
    }

    private function createElectronicInvoice(): Invoice
    {
        $Invoice = $this->createInvoice(false);
        $Customer = new QUI\ERP\User([
            'uuid' => QUI\Utils\Uuid::get(),
            'firstname' => 'PHPUnit',
            'lastname' => 'Customer',
            'country' => 'DE',
            'isNetto' => 1,
            'address' => [
                'firstname' => 'PHPUnit',
                'lastname' => 'Customer',
                'street_no' => 'Teststraße 1',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE',
                'email' => 'phpunit@example.invalid'
            ]
        ]);
        $Articles = new QUI\ERP\Accounting\ArticleList();
        $Articles->setUser($Customer);
        $Articles->addArticle(new QUI\ERP\Accounting\Article([
            'id' => 1,
            'articleNo' => 'MCP-TEST',
            'title' => 'PHPUnit invoice article',
            'quantity' => 1,
            'unitPrice' => 10,
            'vat' => 19
        ]));

        QUI::getDataBaseConnection()->update(Handler::getInstance()->invoiceTable(), [
            'customer_id' => $Customer->getUUID(),
            'customer_data' => json_encode($Customer->getAttributes(), JSON_THROW_ON_ERROR),
            'invoice_address' => json_encode($Customer->getAddress()->getAttributes(), JSON_THROW_ON_ERROR),
            'articles' => $Articles->toUniqueList()->toJSON()
        ], ['hash' => $Invoice->getUUID()]);

        $this->cachePrefixes[] = 'quiqqer/invoice/mcp/download/' . hash('sha256', json_encode([
            QUI::getUsers()->getSystemUser()->getUUID(),
            $Invoice->getUUID(),
            false,
            'xrechnung'
        ], JSON_THROW_ON_ERROR)) . '/';

        return Handler::getInstance()->getInvoice($Invoice->getUUID());
    }

    private function downloadCallback(?string $content, int $renderCount = 1): callable
    {
        $Provider = $this->getMockBuilder(McpProvider::class)->onlyMethods(['createInvoicePdf'])->getMock();

        if ($content === null) {
            $Provider->expects(self::never())->method('createInvoicePdf');
        } else {
            $Provider->expects(self::exactly($renderCount))->method('createInvoicePdf')->willReturn([
                'content' => $content,
                'filename' => 'invoice.pdf'
            ]);
        }

        return $this->registerDownload($Provider);
    }

    private function registerDownload(McpProvider $Provider): callable
    {
        $Builder = new Builder();
        $Provider->register($Builder);

        return $Builder->getTools()['invoice_download']['callback'];
    }

    private function assertSuccessfulResult(mixed $result): void
    {
        self::assertIsArray(
            $result,
            ToolHelper::getLastException() instanceof \Throwable ? ToolHelper::getLastException()->getMessage() : ''
        );
    }

    private function createInvoice(bool $draft, ?string $hash = null): Invoice | InvoiceTemporary
    {
        $Handler = Handler::getInstance();
        $Connection = QUI::getDataBaseConnection();
        $hash ??= QUI\Utils\Uuid::get();
        $Currency = QUI\ERP\Defaults::getCurrency();
        $table = $draft ? $Handler->temporaryInvoiceTable() : $Handler->invoiceTable();
        $data = [
            'hash' => $hash,
            'global_process_id' => $this->processId,
            'customer_id' => '',
            'type' => $draft ? QUI\ERP\Constants::TYPE_INVOICE_TEMPORARY : QUI\ERP\Constants::TYPE_INVOICE,
            'paid_status' => QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
            'c_user' => QUI::getUsers()->getSystemUser()->getUUID(),
            'payment_method' => '-1',
            'payment_data' => QUI\Security\Encryption::encrypt('[]'),
            'currency' => $Currency->getCode(),
            'currency_data' => json_encode($Currency->toArray(), JSON_THROW_ON_ERROR),
            'articles' => '[]',
            'isbrutto' => 0,
            'nettosum' => 0,
            'nettosubsum' => 0,
            'subsum' => 0,
            'sum' => 0,
            'vat_array' => '[]'
        ];

        if (!$draft) {
            $data['id_prefix'] = 'MCP-';
            $data['ordered_by_name'] = '';
            $data['c_username'] = 'phpunit';
        }

        $Connection->insert($table, $data);
        $id = (int)$Connection->lastInsertId();

        if (!$draft) {
            $Connection->update($table, ['id_with_prefix' => 'MCP-' . $id], ['id' => $id]);
        }

        $this->cachePrefixes[] = 'quiqqer/invoice/mcp/download/' . hash('sha256', json_encode([
            QUI::getUsers()->getSystemUser()->getUUID(),
            $hash,
            $draft,
            'pdf'
        ], JSON_THROW_ON_ERROR)) . '/';

        return $draft ? $Handler->getTemporaryInvoice($hash) : $Handler->getInvoice($hash);
    }
}
