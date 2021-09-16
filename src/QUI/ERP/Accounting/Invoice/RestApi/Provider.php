<?php

namespace QUI\ERP\Accounting\Invoice\ProcessingStatus;

use Psr\Http\Message\ResponseInterface as ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as RequestInterface;
use QUI;
use QUI\REST\Response;
use QUI\REST\Server;
use Slim\Routing\RouteCollectorProxy;
use QUI\REST\Utils\RequestUtils;
use QUI\ERP\Accounting\Invoice\Factory as InvoiceFactory;
use QUI\ERP\Currency\Handler as CurrencyHandler;

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
    const ERROR_CODE_PARAMETER_INVALID  = 4002;
    const ERROR_CODE_SERVER_ERROR       = 5001;

    /**
     * @param Server $Server
     */
    public function register(Server $Server)
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
     * @return Response
     */
    public function createInvoice(RequestInterface $Request, ResponseInterface $Response, array $args): Response
    {
        $invoiceData = RequestUtils::getFieldFromRequest($Request, 'invoiceData');

        if (empty($invoiceData)) {
            return $this->getClientErrorResponse(
                'Missing field "invoiceData".',
                self::ERROR_CODE_MISSING_PARAMETERS
            );
        }

        $invoiceData = \json_decode($invoiceData, true);

        if (!\is_array($invoiceData) || \json_last_error() !== \JSON_ERROR_NONE) {
            return $this->getClientErrorResponse(
                'Field "invoiceData" is invalid JSON string.',
                self::ERROR_CODE_PARAMETER_INVALID
            );
        }

        /**
         * Maps invoice fields to required status.
         */
        $invoiceFields = [
            'customer_id'    => false,
            'customer_data'  => false,
//            'ordered_by_name' => false,
            'contact_person' => false,
            'currency'       => false,

//            'invoice_address_id' => false,
//            'invoice_address'    => false,
//            'delivery_address'   => false,

            'articles' => true,

            'paid_status' => false,
            'paid_date'   => false,

            'project_name' => false,
            'comments'     => false,

            'post' => true
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
                    'Missing "'.$field.'" field in "invoiceData".',
                    self::ERROR_CODE_MISSING_PARAMETERS
                );
            }
        }

        $articlesFields = [
            'title'       => true,
            'articleNo'   => false,
            'description' => false,
            'unitPrice'   => true,
            'quantity'    => true,
            'vat'         => false
        ];

        $articles = $invoiceData['articles'];

        foreach ($articlesFields as $field => $isRequired) {
            foreach ($articles as $k => $article) {
                if ($isRequired && !isset($article[$field])) {
                    return $this->getClientErrorResponse(
                        'Missing "'.$field.'" field in article at index '.$k.'.',
                        self::ERROR_CODE_MISSING_PARAMETERS
                    );
                }
            }
        }

        $Factory    = InvoiceFactory::getInstance();
        $Users      = QUI::getUsers();
        $SystemUser = $Users->getSystemUser();

        $InvoiceDraft = $Factory->createInvoice($SystemUser);

        // Customer
        if (!empty($invoiceData['customer_id'])) {
            try {
                $User = $Users->get((int)$invoiceData['customer_id']);
                $InvoiceDraft->setAttribute('customer_id', $User->getId());
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
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

        // Currency
        if (!empty($invoiceData['currency']) && CurrencyHandler::existCurrency($invoiceData['currency'])) {
            $InvoiceDraft->setCurrency($invoiceData['currency']);
        }

        // Commenty
        if (!empty($invoiceData['comments']) && \is_array($invoiceData['comments'])) {
            foreach ($invoiceData['comments'] as $comment) {
                if (\is_string($comment)) {
                    try {
                        $InvoiceDraft->addComment($comment);
                    } catch (\Exception $Exception) {
                        QUI\System\Log::writeException($Exception);
                    }
                }
            }
        }

        try {
            $InvoiceDraft->update($SystemUser);
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);

            return $this->getServerErrorResponse(
                'Invoice draft could not be updated. Please contact an administrator.'
            );
        }

        // Post
        $invoiceId = $InvoiceDraft->getId();

        if (!empty($invoiceData['post'])) {
            try {
                $Invoice   = $InvoiceDraft->post($SystemUser);
                $invoiceId = $Invoice->getId();
            } catch (\Exception $Exception) {
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

                if (\in_array((int)$invoiceData['paid_status'], $validStatusses)) {
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
    public function getOpenApiDefinitionFile()
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
     * @return Response
     */
    protected function getClientErrorResponse(string $msg, int $errorCode): Response
    {
        $Response = new Response(400);

        $Body = $Response->getBody();
        $body = [
            'msg'       => $msg,
            'error'     => true,
            'errorCode' => $errorCode
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string|array $msg
     * @return Response
     */
    protected function getSuccessResponse($msg): Response
    {
        $Response = new Response(200);

        $Body = $Response->getBody();
        $body = [
            'msg'   => $msg,
            'error' => false
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }

    /**
     * Get generic Response with Exception code and message
     *
     * @param string $msg (optional)
     * @return Response
     */
    protected function getServerErrorResponse(string $msg = ''): Response
    {
        $Response = new Response(500);

        $Body = $Response->getBody();
        $body = [
            'msg'       => $msg,
            'error'     => true,
            'errorCode' => self::ERROR_CODE_SERVER_ERROR
        ];

        $Body->write(json_encode($body));

        return $Response->withHeader('Content-Type', 'application/json')->withBody($Body);
    }
}
