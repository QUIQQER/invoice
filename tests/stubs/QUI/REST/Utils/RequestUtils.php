<?php

namespace QUI\REST\Utils;

use Psr\Http\Message\ServerRequestInterface;

use function is_array;
use function json_decode;

if (!class_exists(RequestUtils::class)) {
    class RequestUtils
    {
        public static function getFieldFromRequest(
            ServerRequestInterface $Request,
            string $key
        ): bool|string|array {
            $queryParams = $Request->getQueryParams();

            if (!empty($queryParams[$key])) {
                return $queryParams[$key];
            }

            $postParams = $Request->getParsedBody();

            if (is_array($postParams) && !empty($postParams[$key])) {
                return $postParams[$key];
            }

            $Request->getBody()->rewind();
            $requestBody = json_decode($Request->getBody()->getContents(), true);

            if (is_array($requestBody) && !empty($requestBody[$key])) {
                return $requestBody[$key];
            }

            return false;
        }
    }
}
