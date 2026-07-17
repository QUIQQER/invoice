<?php

namespace QUI\REST;

if (!class_exists(Response::class)) {
    class Response extends \GuzzleHttp\Psr7\Response
    {
        public function write(string $body): Response
        {
            $this->getBody()->write($body);

            return $this;
        }
    }
}
