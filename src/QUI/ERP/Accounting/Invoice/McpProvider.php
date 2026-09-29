<?php

/**
 * This file contains QUI\ERP\Accounting\Invoice\McpProvider
 */

namespace QUI\ERP\Accounting\Invoice;

use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use horstoeko\zugferd\ZugferdProfiles;
use Mcp;
use Mcp\Server\Builder;
use QUI;
use QUI\AI\MCP\ProviderInterface;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\Cache\Manager as Cache;
use QUI\ERP\Accounting\Invoice\Output\OutputProviderInvoice;
use QUI\ERP\Accounting\Invoice\Search\InvoiceSearch;
use QUI\ERP\Accounting\Invoice\Utils\Invoice as InvoiceUtils;
use QUI\Permissions\Permission;

use function array_key_exists;
use function count;
use function date;
use function is_array;
use function is_numeric;
use function is_string;
use function json_decode;
use function json_last_error;
use function max;
use function min;
use function strtotime;

/**
 * MCP provider for invoice access and draft management.
 */
class McpProvider implements ProviderInterface
{
    private const PERMISSION = 'quiqqer.invoice.mcp';
    private const MAX_DOWNLOAD_BYTES = 5_242_880;
    private const DOWNLOAD_SNAPSHOT_TTL = 900;

    public function register(Builder $serverBuilder): void
    {
        if (!$this->canUseMcp()) {
            return;
        }

        $temporaryInvoiceDataSchema = $this->getTemporaryInvoiceDataSchema();

        $serverBuilder->addTool(
            function (string $invoiceId, bool $includeArticles = true): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $this->checkPermission();

                    $Invoice = $this->getInvoice($invoiceId);

                    return $this->parseInvoice($Invoice, $includeArticles);
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_get',
            description: 'Returns one invoice by numeric id, prefixed invoice number, or UUID/hash.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['invoiceId'],
                'properties' => [
                    'invoiceId' => [
                        'type' => 'string',
                        'description' => 'Invoice identifier. Accepts numeric id, prefixed invoice number, '
                            . 'or UUID/hash.'
                    ],
                    'includeArticles' => [
                        'type' => 'boolean',
                        'description' => 'Include invoice articles in the response.',
                        'default' => true
                    ]
                ]
            ]
        );

        $serverBuilder->addTool(
            function (
                string $query = '',
                int $limit = 20,
                int $offset = 0,
                string $order = 'id DESC',
                int | null $paidStatus = null,
                string $customerId = '',
                string $from = '',
                string $to = '',
                string $currency = ''
            ): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $this->checkPermission();

                    return $this->searchInvoices(
                        $query,
                        $limit,
                        $offset,
                        $order,
                        $paidStatus,
                        $customerId,
                        $from,
                        $to,
                        $currency
                    );
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_search',
            description: 'Searches posted invoices and returns compact invoice summaries.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'query' => [
                        'type' => 'string',
                        'description' => 'Free text search across invoice number, UUID/hash, customer, order, '
                            . 'address, project, payment and totals.',
                        'default' => ''
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of invoices to return.',
                        'default' => 20,
                        'minimum' => 1,
                        'maximum' => 100
                    ],
                    'offset' => [
                        'type' => 'integer',
                        'description' => 'Zero-based result offset.',
                        'default' => 0,
                        'minimum' => 0
                    ],
                    'order' => [
                        'type' => 'string',
                        'description' => 'Sort order. Allowed fields are validated by the invoice search '
                            . 'implementation.',
                        'default' => 'id DESC'
                    ],
                    'paidStatus' => [
                        'type' => 'integer',
                        'description' => 'Payment status id: 0=open, 1=paid, 2=partial, 5=canceled, 11=debit.',
                        'enum' => [0, 1, 2, 5, 11]
                    ],
                    'customerId' => [
                        'type' => 'string',
                        'description' => 'Customer id/customer number filter.',
                        'default' => ''
                    ],
                    'from' => [
                        'type' => 'string',
                        'description' => 'Invoice date lower bound. Accepts YYYY-MM-DD or a Unix timestamp.',
                        'default' => ''
                    ],
                    'to' => [
                        'type' => 'string',
                        'description' => 'Invoice date upper bound. Accepts YYYY-MM-DD or a Unix timestamp.',
                        'default' => ''
                    ],
                    'currency' => [
                        'type' => 'string',
                        'description' => 'Currency code filter. Empty value uses the default currency search '
                            . 'behaviour.',
                        'default' => ''
                    ]
                ]
            ]
        );

        $serverBuilder->addTool(
            function (array $data = []): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $User = Server::getRequestUser();

                    Permission::checkPermission('quiqqer.invoice.create', $User);

                    $TemporaryInvoice = Factory::getInstance()->createInvoice($User);

                    if (!empty($data)) {
                        $this->applyTemporaryInvoiceData($TemporaryInvoice, $data);
                        $TemporaryInvoice->save($User);
                    }

                    return $this->parseInvoice($TemporaryInvoice, true);
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_temporary_create',
            description: 'Creates a temporary invoice draft and optionally applies initial invoice data.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'data' => $temporaryInvoiceDataSchema
                ]
            ]
        );

        $serverBuilder->addTool(
            function (string $invoiceId, array $data): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $TemporaryInvoice = $this->getTemporaryInvoice($invoiceId);

                    $this->applyTemporaryInvoiceData($TemporaryInvoice, $data);
                    $TemporaryInvoice->save(Server::getRequestUser());

                    return $this->parseInvoice($TemporaryInvoice, true);
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_temporary_update',
            description: 'Updates a temporary invoice draft.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['invoiceId', 'data'],
                'properties' => [
                    'invoiceId' => [
                        'type' => 'string',
                        'description' => 'Temporary invoice identifier. Accepts numeric id, prefixed number, '
                            . 'or UUID/hash.'
                    ],
                    'data' => $temporaryInvoiceDataSchema
                ]
            ]
        );

        $serverBuilder->addTool(
            function (
                string $invoiceId,
                bool $sendMail = false
            ): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $Settings = Settings::getInstance();
                    $currentSetting = $Settings->sendMailAtInvoiceCreation();
                    $Settings->set('invoice', 'sendMailAtCreation', $sendMail);

                    try {
                        $TemporaryInvoice = $this->getTemporaryInvoice($invoiceId);
                        $Invoice = $TemporaryInvoice->post(Server::getRequestUser());
                    } finally {
                        $Settings->set('invoice', 'sendMailAtCreation', $currentSetting);
                    }

                    return $this->parseInvoice($Invoice, true);
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_temporary_post',
            description: 'Posts a temporary invoice draft and returns the created invoice.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['invoiceId'],
                'properties' => [
                    'invoiceId' => [
                        'type' => 'string',
                        'description' => 'Temporary invoice identifier. Accepts numeric id, prefixed number, '
                            . 'or UUID/hash.'
                    ],
                    'sendMail' => [
                        'type' => 'boolean',
                        'description' => 'Send the invoice creation mail while posting.',
                        'default' => false
                    ]
                ]
            ]
        );

        $this->registerDownload($serverBuilder);
    }

    private function registerDownload(Builder $serverBuilder): void
    {
        $serverBuilder->addTool(
            function (
                string $invoiceId,
                bool $draft = false,
                int $offset = 0,
                int $maxBytes = self::MAX_DOWNLOAD_BYTES,
                ?string $downloadId = null,
                ?string $format = null
            ): array | Mcp\Schema\Result\CallToolResult {
                try {
                    $this->checkPermission();

                    $format ??= $this->getDefaultDownloadFormat();

                    if (!in_array($format, ['pdf', 'xrechnung', 'zugferd'], true)) {
                        throw new QUI\Exception('Unsupported invoice format. Use pdf, xrechnung or zugferd.');
                    }

                    $Invoice = $draft
                        ? $this->getTemporaryInvoice($invoiceId)
                        : Handler::getInstance()->getInvoice($invoiceId);

                    return $this->downloadInvoiceFile($Invoice, $draft, $offset, $maxBytes, $downloadId, $format);
                } catch (\Throwable $Exception) {
                    return ToolHelper::parseExceptionToResult($Exception);
                }
            },
            name: 'invoice_download',
            description: 'Downloads an invoice as PDF, XRechnung 3.0 XML or ZUGFeRD PDF. '
                . 'The default follows invoice.zugferdInvoiceAttachment (ZUGFeRD when enabled or unset, PDF otherwise). '
                . 'Set draft=true for a temporary invoice. Returns Base64 chunks of up to 5 MiB. '
                . 'For subsequent chunks pass downloadId and download.nextOffset with the same invoice, draft flag and format. '
                . 'Downloads expire after 15 minutes; restart without downloadId.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['invoiceId'],
                'properties' => [
                    'invoiceId' => [
                        'type' => 'string',
                        'description' => 'Numeric id, prefixed invoice number, or UUID/hash.'
                    ],
                    'draft' => [
                        'type' => 'boolean',
                        'default' => false,
                        'description' => 'Download a temporary invoice draft instead of a posted invoice.'
                    ],
                    'format' => [
                        'type' => 'string',
                        'enum' => ['pdf', 'xrechnung', 'zugferd'],
                        'default' => $this->getDefaultDownloadFormat(),
                        'description' => 'pdf: standard PDF without embedded invoice XML, using the configured ERP template; '
                            . 'xrechnung: standalone XRechnung 3.0 XML (CII); '
                            . 'zugferd: PDF with embedded invoice XML using invoice.zugferdInvoiceAttachmentType '
                            . '(EN16931 when unset). Omit format to follow invoice.zugferdInvoiceAttachment; '
                            . 'if that setting is missing, ZUGFeRD is used. An explicit format overrides the setting. '
                            . 'Electronic formats require complete invoice and customer data.'
                    ],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'default' => 0],
                    'maxBytes' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => self::MAX_DOWNLOAD_BYTES,
                        'default' => self::MAX_DOWNLOAD_BYTES
                    ],
                    'downloadId' => [
                        'type' => 'string',
                        'pattern' => '^[a-f0-9]{64}$',
                        'description' => 'ID returned by the first chunk. Required when offset is greater than zero.'
                    ]
                ]
            ]
        );
    }

    private function getDefaultDownloadFormat(): string
    {
        $settings = Settings::getConfig()->toArray();

        return ($settings['invoice']['zugferdInvoiceAttachment'] ?? true) ? 'zugferd' : 'pdf';
    }

    /**
     * @return array<string, mixed>
     */
    private function downloadInvoiceFile(
        Invoice | InvoiceTemporary $Invoice,
        bool $draft,
        int $offset,
        int $maxBytes,
        ?string $downloadId,
        string $format
    ): array {
        $offset = max(0, $offset);
        $maxBytes = min(self::MAX_DOWNLOAD_BYTES, max(1, $maxBytes));
        $cachePrefix = 'quiqqer/invoice/mcp/download/' . hash('sha256', json_encode([
            Server::getRequestUser()->getUUID(),
            $Invoice->getUUID(),
            $draft,
            $format
        ], JSON_THROW_ON_ERROR)) . '/';

        if ($downloadId !== null) {
            if (!preg_match('/\A[a-f0-9]{64}\z/', $downloadId)) {
                throw new QUI\Exception('Invalid invoice download ID.');
            }

            $Snapshot = Cache::getStash($cachePrefix . $downloadId);
            $file = $Snapshot->get();

            if ($Snapshot->isMiss()) {
                throw new QUI\Exception('Invoice download expired or unavailable. Restart without downloadId.', 404);
            }

            if (
                !is_array($file)
                || !isset($file['content'], $file['filename'])
                || !is_string($file['content'])
                || !is_string($file['filename'])
            ) {
                throw new QUI\Exception('Invalid invoice download snapshot. Restart without downloadId.');
            }
        } else {
            if ($offset !== 0) {
                throw new QUI\Exception('Continuing an invoice download requires downloadId.');
            }

            $file = $this->createInvoiceFile($Invoice, $format);
            $downloadId = bin2hex(random_bytes(32));

            // Preserve the exact bytes across chunks, even if the draft changes after rendering.
            if (strlen($file['content']) > $maxBytes) {
                // Stash also retains download state when the general content cache is disabled.
                $Snapshot = Cache::getStash($cachePrefix . $downloadId);
                $Snapshot->set($file);
                $Snapshot->expiresAfter(self::DOWNLOAD_SNAPSHOT_TTL);

                if (!$Snapshot->save()) {
                    throw new QUI\Exception('Could not store invoice download for subsequent chunks.');
                }
            }
        }

        $size = strlen($file['content']);

        if ($offset > $size) {
            throw new QUI\Exception('Download offset exceeds the file size.');
        }

        $content = substr($file['content'], $offset, $maxBytes);
        $chunkSize = strlen($content);
        $nextOffset = $offset + $chunkSize;
        $complete = $nextOffset >= $size;

        return [
            'invoiceId' => $Invoice->getId(),
            'invoiceHash' => $Invoice->getUUID(),
            'prefixedNumber' => $Invoice->getPrefixedNumber(),
            'draft' => $draft,
            'format' => $format,
            'downloadId' => $downloadId,
            'download' => [
                'filename' => $file['filename'],
                'mimeType' => $format === 'xrechnung' ? 'application/xml' : 'application/pdf',
                'size' => $size,
                'encoding' => 'base64',
                'offset' => $offset,
                'chunkSize' => $chunkSize,
                'nextOffset' => $complete ? null : $nextOffset,
                'complete' => $complete,
                'contentBase64' => base64_encode($content)
            ]
        ];
    }

    /**
     * @return array{content: string, filename: string}
     */
    private function createInvoiceFile(Invoice | InvoiceTemporary $Invoice, string $format): array
    {
        if ($format === 'xrechnung') {
            return [
                'content' => InvoiceUtils::getElectronicInvoice($Invoice, ZugferdProfiles::PROFILE_XRECHNUNG_3)
                    ->getContent(),
                'filename' => InvoiceUtils::getInvoiceFilename($Invoice) . '.xml'
            ];
        }

        $pdf = $this->createInvoicePdf($Invoice);

        if ($format === 'zugferd') {
            $settings = Settings::getConfig()->toArray();
            $profile = (int)($settings['invoice']['zugferdInvoiceAttachmentType'] ?? ZugferdProfiles::PROFILE_EN16931);
            $ElectronicInvoice = InvoiceUtils::getElectronicInvoice($Invoice, $profile);
            $PdfBuilder = new ZugferdDocumentPdfBuilder($ElectronicInvoice, $pdf['content']);
            $pdf['content'] = $PdfBuilder->generateDocument()->downloadString();
        }

        return $pdf;
    }

    /**
     * @return array{content: string, filename: string}
     */
    protected function createInvoicePdf(Invoice | InvoiceTemporary $Invoice): array
    {
        $Document = $Invoice->getView()->toPDF();
        // The selected download format controls embedding, independently of the mail/PDF defaults.
        $Document->setAttribute(EventHandler::PDF_SKIP_ELECTRONIC_INVOICE, true);
        $PdfHandler = new QUI\HtmlToPdf\Handler();

        // Older HTML-to-PDF 4.x versions expose rendering through the document itself.
        if (in_array('getPdfCreator', get_class_methods($PdfHandler), true)) {
            $path = $PdfHandler->getPdfCreator()->createPdf($Document);
        } else {
            $path = $Document->createPDF();
        }

        try {
            $content = file_get_contents($path);

            if ($content === false) {
                throw new QUI\Exception('Could not read generated invoice PDF.');
            }

            return [
                'content' => $content,
                'filename' => OutputProviderInvoice::getDownloadFileName($Invoice->getUUID()) . '.pdf'
            ];
        } finally {
            QUI\Utils\System\File::unlink($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getTemporaryInvoiceDataSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Temporary invoice draft data. Only provided fields are changed.',
            'additionalProperties' => true,
            'properties' => [
                'customer_id' => [
                    'type' => 'string',
                    'description' => 'QUIQQER user/customer UUID or id. Empty value removes the customer.'
                ],
                'invoice_address_id' => [
                    'type' => 'string',
                    'description' => 'Invoice address UUID/id of the selected customer.'
                ],
                'invoice_address' => $this->getAddressSchema('Invoice address data.'),
                'addressDelivery' => $this->getAddressSchema(
                    'Delivery address data. Empty object or empty value removes the delivery address.'
                ),
                'articles' => [
                    'type' => 'array',
                    'description' => 'Full article list for the draft. Providing this field replaces all articles.',
                    'items' => $this->getArticleSchema()
                ],
                'priceFactors' => [
                    'type' => 'array',
                    'description' => 'Optional price factor list such as discounts or surcharges.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => true
                    ]
                ],
                'currency' => [
                    'type' => 'string',
                    'description' => 'Invoice currency code, for example EUR.'
                ],
                'currencyRate' => [
                    'type' => 'number',
                    'description' => 'Exchange rate for the selected invoice currency.'
                ],
                'payment_method' => [
                    'type' => 'integer',
                    'description' => 'Payment method id.'
                ],
                'paid_status' => [
                    'type' => 'integer',
                    'description' => 'Payment status id: 0=open, 1=paid, 2=partial, 4=error, 5=canceled, 11=debit.',
                    'enum' => [0, 1, 2, 4, 5, 11]
                ],
                'paid_date' => [
                    'type' => 'string',
                    'description' => 'Paid date as date/datetime string or timestamp accepted by QUIQQER.'
                ],
                'project_name' => [
                    'type' => 'string',
                    'description' => 'Project or invoice title.'
                ],
                'customer_reference' => [
                    'type' => 'string',
                    'description' => 'Customer reference text.'
                ],
                'contact_person' => [
                    'type' => 'string',
                    'description' => 'Contact person text for the invoice.'
                ],
                'additional_invoice_text' => [
                    'type' => 'string',
                    'description' => 'Additional invoice text.'
                ],
                'service_period' => [
                    'type' => 'string',
                    'description' => 'Delivery date or service period. Accepts YYYY-MM-DD, DD.MM.YYYY or a range like YYYY-MM-DD - YYYY-MM-DD.'
                ],
                'processing_status' => [
                    'type' => 'integer',
                    'description' => 'Invoice processing status id.'
                ],
                'time_for_payment' => [
                    'type' => 'integer',
                    'description' => 'Payment term in days.'
                ],
                'date' => [
                    'type' => 'string',
                    'description' => 'Invoice date/datetime. Posting respects it only if the user may change dates.'
                ],
                'order_id' => [
                    'type' => 'string',
                    'description' => 'Related order id.'
                ],
                'ordered_by' => [
                    'type' => 'string',
                    'description' => 'UUID/id of the ordering user.'
                ]
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getArticleSchema(): array
    {
        return [
            'type' => 'object',
            'description' => 'Invoice article. At minimum quantity should be provided.',
            'additionalProperties' => true,
            'properties' => [
                'title' => [
                    'type' => 'string',
                    'description' => 'Article title.'
                ],
                'articleNo' => [
                    'type' => 'string',
                    'description' => 'Article number.'
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Article description.'
                ],
                'quantity' => [
                    'type' => 'number',
                    'description' => 'Article quantity.',
                    'minimum' => 0
                ],
                'unitPrice' => [
                    'type' => 'number',
                    'description' => 'Unit price.'
                ],
                'vat' => [
                    'type' => 'number',
                    'description' => 'VAT rate in percent.'
                ],
                'productId' => [
                    'type' => 'integer',
                    'description' => 'Optional QUIQQER product id.'
                ],
                'quiqqerProductId' => [
                    'type' => 'integer',
                    'description' => 'Optional QUIQQER product id used by REST imports.'
                ],
                'class' => [
                    'type' => 'string',
                    'description' => 'Optional article class. Must extend QUI\\ERP\\Accounting\\Article.'
                ]
            ]
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getAddressSchema(string $description): array
    {
        return [
            'type' => 'object',
            'description' => $description,
            'additionalProperties' => true,
            'properties' => [
                'company' => [
                    'type' => 'string',
                    'description' => 'Company name.'
                ],
                'salutation' => [
                    'type' => 'string',
                    'description' => 'Salutation.'
                ],
                'firstname' => [
                    'type' => 'string',
                    'description' => 'First name.'
                ],
                'lastname' => [
                    'type' => 'string',
                    'description' => 'Last name.'
                ],
                'street_no' => [
                    'type' => 'string',
                    'description' => 'Street and number.'
                ],
                'zip' => [
                    'type' => 'string',
                    'description' => 'ZIP/postal code.'
                ],
                'city' => [
                    'type' => 'string',
                    'description' => 'City.'
                ],
                'country' => [
                    'type' => 'string',
                    'description' => 'Country code.'
                ],
                'contactEmail' => [
                    'type' => 'string',
                    'description' => 'Contact email address.'
                ]
            ]
        ];
    }

    private function canUseMcp(): bool
    {
        try {
            $this->checkPermission();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @throws QUI\Exception
     */
    private function checkPermission(): void
    {
        Permission::checkPermission(self::PERMISSION, Server::getRequestUser());
    }

    /**
     * @throws QUI\Exception
     */
    private function getInvoice(string $invoiceId): Invoice | InvoiceTemporary
    {
        $Handler = Handler::getInstance();

        try {
            return $Handler->get($invoiceId);
        } catch (QUI\Exception) {
            return $Handler->getInvoiceByHash($invoiceId);
        }
    }

    /**
     * @throws QUI\Exception
     */
    private function getTemporaryInvoice(string $invoiceId): InvoiceTemporary
    {
        return InvoiceUtils::getTemporaryInvoiceByString($invoiceId);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws QUI\Exception
     */
    private function applyTemporaryInvoiceData(InvoiceTemporary $Invoice, array $data): void
    {
        $resetInvoiceAddress = false;

        if (array_key_exists('customer_id', $data)) {
            if (empty($data['customer_id'])) {
                $data['invoice_address_id'] = '';
                $data['invoice_address'] = '';
            } else {
                try {
                    $Invoice->setCustomer(QUI::getUsers()->get($data['customer_id']));
                } catch (\Exception) {
                }
            }

            $resetInvoiceAddress = true;
        }

        if (array_key_exists('addressDelivery', $data) && !empty($data['addressDelivery'])) {
            $delivery = $data['addressDelivery'];
            unset($data['addressDelivery']);

            if (is_string($delivery)) {
                $delivery = json_decode($delivery, true) ?? [];
            }

            if (is_array($delivery)) {
                $Invoice->setDeliveryAddress($delivery);
            }
        } elseif (array_key_exists('addressDelivery', $data)) {
            unset($data['addressDelivery']);
            $Invoice->removeDeliveryAddress();
        }

        if (array_key_exists('articles', $data) && is_array($data['articles'])) {
            $articles = $data['articles'];
            unset($data['articles']);

            $Invoice->clearArticles();
            $Invoice->importArticles($articles);
        }

        if (isset($data['priceFactors']) && is_array($data['priceFactors'])) {
            try {
                $List = new QUI\ERP\Accounting\PriceFactors\FactorList($data['priceFactors']);
                $Invoice->getArticles()->importPriceFactors($List);
            } catch (QUI\Exception) {
            }
        }

        if (isset($data['currency']) && is_string($data['currency'])) {
            $Invoice->setCurrency($data['currency']);
        }

        if (!empty($data['currencyRate'])) {
            $Currency = $Invoice->getCurrency();
            $Currency->setExchangeRate((float)$data['currencyRate']);
            $Invoice->setCurrency($Currency);
        }

        if (array_key_exists('service_period', $data)) {
            $data['service_period'] = InvoiceUtils::normalizeServicePeriod($data['service_period']);
        }

        if (
            $resetInvoiceAddress
            || array_key_exists('invoice_address', $data)
            || array_key_exists('invoice_address_id', $data)
        ) {
            $Invoice->setAttribute('invoice_address', false);
        }

        $Invoice->setAttributes($data);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws QUI\Exception
     */
    private function searchInvoices(
        string $query,
        int $limit,
        int $offset,
        string $order,
        int | null $paidStatus,
        string $customerId,
        string $from,
        string $to,
        string $currency
    ): array {
        $limit = min(max($limit, 1), 100);
        $offset = max($offset, 0);

        $Search = InvoiceSearch::getInstance();
        $Search->clearFilter();
        $Search->setFilter('search', $query);
        $Search->setFilter('currency', $currency);
        $Search->limit($offset, $limit);
        $Search->order($order);

        if ($paidStatus !== null) {
            $Search->setFilter('paid_status', (string)$paidStatus);
        }

        if ($customerId !== '') {
            $Search->setFilter('customer_id', $customerId);
        }

        if ($from !== '') {
            $Search->setFilter('from', (string)$this->parseDateFilter($from, true));
        }

        if ($to !== '') {
            $Search->setFilter('to', (string)$this->parseDateFilter($to, false));
        }

        $entries = $Search->search();
        $result = [];

        foreach ($entries as $entry) {
            if (!isset($entry['id'])) {
                continue;
            }

            try {
                $result[] = $this->parseInvoice(
                    Handler::getInstance()->getInvoice($entry['id']),
                    false
                );
            } catch (QUI\Exception) {
                continue;
            }
        }

        return [
            'offset' => $offset,
            'limit' => $limit,
            'count' => count($result),
            'invoices' => $result
        ];
    }

    private function parseDateFilter(string $value, bool $startOfDay): string | int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        return $startOfDay
            ? date('Y-m-d 00:00:00', $timestamp)
            : date('Y-m-d 23:59:59', $timestamp);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws QUI\Exception
     */
    private function parseInvoice(Invoice | InvoiceTemporary $Invoice, bool $includeArticles): array
    {
        try {
            QUI\ERP\Accounting\Calc::calculatePayments($Invoice);
        } catch (\Throwable) {
            // Payment calculation is helpful for MCP output but must not hide the invoice itself.
        }

        $data = $Invoice->toArray();
        $Currency = $Invoice->getCurrency();

        $result = [
            'id' => $Invoice->getId(),
            'uuid' => $Invoice->getUUID(),
            'prefixedNumber' => $Invoice->getPrefixedNumber(),
            'globalProcessId' => $Invoice->getGlobalProcessId(),
            'type' => $Invoice->getInvoiceType(),
            'customerId' => $data['customer_id'] ?? null,
            'customerData' => $this->decodeJson($data['customer_data'] ?? null),
            'invoiceAddress' => $this->decodeJson($data['invoice_address'] ?? null),
            'deliveryAddress' => $this->decodeJson($data['delivery_address'] ?? null),
            'servicePeriod' => $this->decodeJson($data['service_period'] ?? null),
            'servicePeriodDisplay' => InvoiceUtils::getServicePeriodDisplayText($Invoice, QUI::getLocale()),
            'date' => $data['date'] ?? null,
            'timeForPayment' => $data['time_for_payment'] ?? null,
            'paidStatus' => isset($data['paid_status']) ? (int)$data['paid_status'] : null,
            'paidDate' => $data['paid_date'] ?? null,
            'currency' => $Currency->getCode(),
            'totals' => [
                'netTotal' => (float)($data['nettosum'] ?? 0),
                'netSubtotal' => (float)($data['nettosubsum'] ?? $data['nettosum'] ?? 0),
                'subtotal' => (float)($data['subsum'] ?? 0),
                'total' => (float)($data['sum'] ?? 0),
                'paid' => (float)($data['paid'] ?? 0),
                'openAmount' => (float)($data['toPay'] ?? 0),
                'vat' => $this->decodeJson($data['vat_array'] ?? null)
            ],
            'orderId' => $data['order_id'] ?? null,
            'projectName' => $data['project_name'] ?? null,
            'paymentMethod' => $data['payment_method'] ?? null,
            'processingStatus' => $data['processing_status'] ?? null,
            'isBrutto' => isset($data['isbrutto']) ? (bool)$data['isbrutto'] : null
        ];

        if ($includeArticles) {
            $articles = $data['articles'] ?? [];

            if (is_string($articles)) {
                $articles = $this->decodeJson($articles) ?? [];
            }

            if (is_array($articles)) {
                $articles = InvoiceUtils::formatArticlesArray($articles);
            }

            $result['articles'] = $articles;
        }

        return $result;
    }

    private function decodeJson(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $value;
        }

        return $decoded;
    }
}
