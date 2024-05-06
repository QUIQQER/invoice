<?php

namespace QUI\ERP\Accounting\Invoice\RestApi;

use Exception;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use QUI;
use QUI\ERP\Accounting\Invoice\Factory as InvoiceFactory;
use QUI\ERP\Accounting\Invoice\ProcessingStatus\Handler as ProcessingStatuses;
use QUI\ERP\Currency\Handler as CurrencyHandler;
use QUI\ExceptionStack;
use QUI\REST\Response;
use QUI\REST\Server;
use QUI\REST\Utils\RequestUtils;
use Slim\Routing\RouteCollectorProxy;

use function define;
use function defined;
use function file_put_contents;
use function hash;
use function hex2bin;
use function in_array;
use function is_array;
use function is_string;
use function pathinfo;

/**
 * Class Provider
 *
 * REST API provider for quiqqer/invoice
 */
class Provider implements QUI\REST\ProviderInterface
{
    /**
     * Error codes
     */
    const ERROR_CODE_MISSING_PARAMETERS = 4001;
    const ERROR_CODE_PARAMETER_INVALID = 4002;
    const ERROR_CODE_SERVER_ERROR = 5001;

    /**
     * @param Server $Server
     */
    public function register(Server $Server): void
    {
        $Slim = $Server->getSlim();

        // Register paths
        $Slim->group('/invoice', function (RouteCollectorProxy $RouteCollector) {
            $RouteCollector->post('/create', [$this, 'createInvoice']);
        });
    }

    /**
     * POST /invoice/create
     *
     * @param RequestInterface $Request
     * @param ResponseInterface $Response
     * @param array $args
     *
     * @return MessageInterface
     * @throws QUI\Database\Exception
     * @throws QUI\ERP\Exception
     * @throws QUI\Exception
     * @throws ExceptionStack
     * @throws QUI\Lock\Exception
     * @throws QUI\Permissions\Exception
     * @throws QUI\Users\Exception
     */
    public function createInvoice(RequestInterface $Request, ResponseInterface $Response, array $args): MessageInterface
    {
        $invoiceData = RequestUtils::getFieldFromRequest($Request, 'invoiceData');

        if (empty($invoiceData)) {
            return $this->getClientErrorResponse(
                'Missing field "invoiceData".',
                self::ERROR_CODE_MISSING_PARAMETERS
            );
        }

        if (!is_array($invoiceData)) {
            return $this->getClientErrorResponse(
                'Field "invoiceData" is invalid JSON string.',
                self::ERROR_CODE_PARAMETER_INVALID
            );
        }

        /**
         * Maps invoice fields to required status.
         */
        $invoiceFields = [
            'customer_no' => false,
            'customer_data' => false,
//            'ordered_by_name' => false,
            'contact_person' => false,

            'invoice_address_id' => false,
//            'invoice_address'    => false,
//            'delivery_address'   => false,

            'articles' => true,

            'paid_status' => false,
            'paid_date' => false,

            'project_name' => false,
            'comments' => false,

            'post' => false,

            'source' => true,

            'currency' => false,
            'payment_method' => false,

            'additional_invoice_text' => false,
            'files' => false,
            'processing_status' => false,
        ];

        // Remove unknown fields
        foreach ($invoiceData as $field => $value) {
            if (!isset($invoiceFields[$field])) {
                unset($invoiceData[$field]);
            }
        }

        foreach ($invoiceFields as $field => $isRequired) {
            if ($isRequired && !isset($invoiceData[$field])) {
                return $this->getClientErrorResponse(
                    'Missing "' . $field . '" field in "invoiceData".',
                    self::ERROR_CODE_MISSING_PARAMETERS
                );
            }
        }

        $articlesFields = [
            'title' => false,
            'articleNo' => false,
            'description' => false,
            'unitPrice' => false,
            'quantity' => true,
            'vat' => false,

            'quiqqerProductId' => false
        ];

        $articles = $invoiceData['articles'];

        foreach ($articlesFields as $field => $isRequired) {
            foreach ($articles as $k => $article) {
                if ($isRequired && !isset($article[$field])) {
                    return $this->getClientErrorResponse(
                        'Missing "' . $field . '" field in article at index ' . $k . '.',
                        self::ERROR_CODE_MISSING_PARAMETERS
                    );
                }
            }
        }

        if (!defined('SYSTEM_INTERN')) {
            define('SYSTEM_INTERN', true);
        }

        $Factory = InvoiceFactory::getInstance();
        $Users = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();

        $InvoiceDraft = $Factory->createInvoice($SystemUser);

        // Customer
        $User = false;

        if (!empty($invoiceData['customer_no'])) {
            try {
                $User = QUI\ERP\Customer\Customers::getInstance()->getCustomerByCustomerNo($invoiceData['customer_no']);
                $InvoiceDraft->setAttribute('customer_id', $User->getUUID());
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }

            if ($User) {
                $Address = false;

                if (!empty($invoiceData['invoice_address_id'])) {
                    try {
                        $Address = $User->getAddress($invoiceData['invoice_address_id']);
                        $InvoiceDraft->setAttribute('invoice_address_id', $Address->getUUID());
                    } catch (Exception $Exception) {
                        QUI\System\Log::writeException($Exception);
                    }
                }

                // Set default address
                if (!$Address) {
                    try {
                        $InvoiceDraft->setAttribute('invoice_address_id', $User->getStandardAddress()->getUUID());
                    } catch (Exception $Exception) {
                        QUI\System\Log::writeException($Exception);
                    }
                }
            }
        }

        // Contact person
        if (!empty($invoiceData['contact_person'])) {
            $InvoiceDraft->setAttribute('contact_person', $invoiceData['contact_person']);
        }

        // Project name
        if (!empty($invoiceData['project_name'])) {
            $InvoiceDraft->setAttribute('project_name', $invoiceData['project_name']);
        }

        // Payment method
        $Payments = QUI\ERP\Accounting\Payments\Payments::getInstance();
        $Customer = $InvoiceDraft->getCustomer();
        $Payment = false;

        if ($Customer) {
            $customerDefaultPaymentId = $Customer->getAttribute('quiqqer.erp.standard.payment');

            if (!empty($customerDefaultPaymentId)) {
                try {
                    $Payment = $Payments->getPayment($customerDefaultPaymentId);
                } catch (Exception $Exception) {
                    QUI\System\Log::writeDebugException($Exception);
                }
            }
        }

        if (!$Payment && !empty($invoiceData['payment_method'])) {
            try {
                $Payment = $Payments->getPayment((int)$invoiceData['payment_method']);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        // Set default payment method
        if (!$Payment) {
            $payments = $Payments->getPayments([
                'where' => [
                    'payment_type' => QUI\ERP\Accounting\Payments\Methods\Invoice\Payment::class
                ]
            ]);

            if (!empty($payments)) {
                $Payment = $payments[0];
            }
        }

        if ($Payment) {
            $InvoiceDraft->setAttribute('payment_method', $Payment->getId());
        }

        // Currency
        if (!empty($invoiceData['currency']) && CurrencyHandler::existCurrency($invoiceData['currency'])) {
            $InvoiceDraft->setCurrency($invoiceData['currency']);
        }

        // Comments
        $InvoiceDraft->addComment(
            QUI::getSystemLocale()->get('quiqqer/invoice', 'RestApi.Provider.invoice.comment.source', [
                'source' => $invoiceData['source']
            ])
        );

        if (!empty($invoiceData['comments']) && is_array($invoiceData['comments'])) {
            foreach ($invoiceData['comments'] as $comment) {
                if (is_string($comment)) {
                    try {
                        $InvoiceDraft->addComment($comment);
                    } catch (Exception $Exception) {
                        QUI\System\Log::writeException($Exception);
                    }
                }
            }
        }

        // Additional invoice text
        if (!empty($invoiceData['additional_invoice_text'])) {
            $invoiceText = '<p>' . $invoiceData['additional_invoice_text'] . '</p>';
            $invoiceText .= QUI::getLocale()->get('quiqqer/invoice', 'additional.invoice.text');

            $InvoiceDraft->setAttribute('additional_invoice_text', $invoiceText);
        }

        // Articles - Existing products
        $ProductList = new QUI\ERP\Products\Product\ProductList();
        $ProductList->setUser($InvoiceDraft->getCustomer());

        foreach ($invoiceData['articles'] as $article) {
            if (empty($article['quiqqerProductId'])) {
                continue;
            }

            try {
                $Product = QUI\ERP\Products\Handler\Products::getProduct((int)$article['quiqqerProductId']);

                $UniqueProduct = $Product->createUniqueProduct($InvoiceDraft->getCustomer());
                $UniqueProduct->setQuantity((float)$article['quantity']);

                $ProductList->addProduct($UniqueProduct);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        $ProductList->recalculate();

        foreach ($ProductList->getProducts() as $Product) {
            $InvoiceDraft->addArticle($Product->toArticle());
        }

        // Articles - Custom
        foreach ($invoiceData['articles'] as $article) {
            if (!empty($article['quiqqerProductId'])) {
                continue;
            }

            $Article = new QUI\ERP\Accounting\Article($article);
            $InvoiceDraft->addArticle($Article);
        }

        // Files
        if ($User && !empty($invoiceData['files'])) {
            $fileDir = QUI::getPackage('quiqqer/invoice')->getVarDir() . 'uploads/' . $InvoiceDraft->getUUID() . '/';
            QUI\Utils\System\File::mkdir($fileDir);

            $fileCounter = 0;

            foreach ($invoiceData['files'] as $file) {
                if (!is_array($file) || !isset($file['name']) || !isset($file['content'])) {
                    continue;
                }

                // Write file data to file
                $localFile = $fileDir;

                if (!empty($file['name'])) {
                    $localFile .= $file['name'];
                } else {
                    $localFile .= 'file_' . ++$fileCounter;
                }

                file_put_contents($localFile, hex2bin($file['content']));

                $fileInfo = pathinfo($localFile);
                $fileHash = hash('sha256', $fileInfo['basename']);

                try {
                    QUI\ERP\Customer\CustomerFiles::addFileToCustomer($User->getUUID(), $localFile);

                    $fileOptions = [];

                    if (!empty($file['options'])) {
                        $fileOptions = $file['options'];
                    }

                    $InvoiceDraft->addCustomerFile($fileHash, $fileOptions);
                } catch (Exception $Exception) {
                    QUI\System\Log::writeException($Exception);
                }
            }
        }

        // Processing status
        if (!empty($invoiceData['processing_status'])) {
            $statusId = (int)$invoiceData['processing_status'];
            $StatusHandler = ProcessingStatuses::getInstance();

            try {
                $StatusHandler->getProcessingStatus($statusId);
                $InvoiceDraft->setAttribute('processing_status', $statusId);
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);
            }
        }

        try {
            $InvoiceDraft->update($SystemUser);
        } catch (Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $this->getServerErrorResponse(
                'Invoice draft could not be updated. Please contact an administrator.'
            );
        }

        // Post
        $invoiceId = $InvoiceDraft->getUUID();

        if (!empty($invoiceData['post'])) {
            try {
                $Invoice = $InvoiceDraft->post($SystemUser);
                $invoiceId = $Invoice->getUUID();
            } catch (Exception $Exception) {
                QUI\System\Log::writeException($Exception);

                return $this->getServerErrorResponse(
                    'Invoice draft could not be posted. Please contact an administrator.'
                );
            }

            // Payment status (only posted invoice)
            if (!empty($invoiceData['paid_status'])) {
                $validStatusses = [
                    QUI\ERP\Constants::PAYMENT_STATUS_OPEN,
                    QUI\ERP\Constants::PAYMENT_STATUS_PAID,
                    QUI\ERP\Constants::PAYMENT_STATUS_PART,
                    QUI\ERP\Constants::PAYMENT_STATUS_ERROR,
                    QUI\ERP\Constants::PAYMENT_STATUS_CANCELED,
                    QUI\ERP\Constants::PAYMENT_STATUS_DEBIT,
                    QUI\ERP\Constants::PAYMENT_STATUS_PLAN
                ];

                if (in_array((int)$invoiceData['paid_status'], $validStatusses)) {
                    $Invoice->setPaymentStatus((int)$invoiceData['paid_status']);
                }
            }
        }

        return $this->getSuccessResponse([
            'invoice_id' => $invoiceId
        ]);
    }

    /**
     * Get file containting OpenApi definition for this API.
     *
     * @return string|false - Absolute file path or false if no definition exists
     */
    public function getOpenApiDefinitionFile(): bool|string
    {
        // @todo
        return false;

//        try {
//            $dir = QUI::getPackage('quiqqer/invoice')->getDir();
//        } catch (\Exception $Exception) {
//            QUI\System\Log::writeException($Exception);
//            return false;
//        }
//
//        return $dir.'src/Pcsg/Briefwahl/Api/OpenApiDefinition.json';
    }

    /**
     * Get unique internal API name.
     *
     * This is required for requesting specific data about an API (i.e. OpenApi definition).
     *
     * @return string - Only letters; no other characters!
     */
    public function getName(): string
    {
        return 'EcoynInvoice';
    }

    /**
     * Get title of this API.
     *
     * @param QUI\Locale|null $Locale (optional)
     * @return string
     */
    public function getTitle(QUI\Locale $Locale = null): string
    {
        if (empty($Locale)) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/invoice', 'RestProvider.title');
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string $msg
     * @param int $errorCode
     * @return MessageInterface
     */
    protected function getClientErrorResponse(string $msg, int $errorCode): MessageInterface
    {
        $Response = new Response(400);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => true,
            'errorCode' => $errorCode
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param array|string $msg
     * @return MessageInterface
     */
    protected function getSuccessResponse(array|string $msg): MessageInterface
    {
        $Response = new Response(200);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => false
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string $msg (optional)
     * @return MessageInterface
     */
    protected function getServerErrorResponse(string $msg = ''): MessageInterface
    {
        $Response = new Response(500);

        $Body = $Response->getBody();
        $body = [
            'msg' => $msg,
            'error' => true,
            'errorCode' => self::ERROR_CODE_SERVER_ERROR
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }
}
