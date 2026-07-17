<?php

namespace QUI\REST;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use function array_merge;
use function implode;
use function strtolower;

if (!class_exists(Response::class)) {
    class Response implements ResponseInterface
    {
        private string $protocolVersion = '1.1';
        private array $headers = [];
        private StreamInterface $body;

        public function __construct(
            private int $statusCode = 200,
            private string $reasonPhrase = ''
        ) {
            $this->body = new ResponseStream();
        }

        public function write(string $body): Response
        {
            $this->body->write($body);

            return $this;
        }

        public function getProtocolVersion(): string
        {
            return $this->protocolVersion;
        }

        public function withProtocolVersion(string $version): MessageInterface
        {
            $Clone = clone $this;
            $Clone->protocolVersion = $version;

            return $Clone;
        }

        public function getHeaders(): array
        {
            return $this->headers;
        }

        public function hasHeader(string $name): bool
        {
            return isset($this->headers[strtolower($name)]);
        }

        public function getHeader(string $name): array
        {
            return $this->headers[strtolower($name)] ?? [];
        }

        public function getHeaderLine(string $name): string
        {
            return implode(', ', $this->getHeader($name));
        }

        public function withHeader(string $name, $value): MessageInterface
        {
            $Clone = clone $this;
            $Clone->headers[strtolower($name)] = (array)$value;

            return $Clone;
        }

        public function withAddedHeader(string $name, $value): MessageInterface
        {
            $Clone = clone $this;
            $key = strtolower($name);
            $Clone->headers[$key] = array_merge($Clone->headers[$key] ?? [], (array)$value);

            return $Clone;
        }

        public function withoutHeader(string $name): MessageInterface
        {
            $Clone = clone $this;
            unset($Clone->headers[strtolower($name)]);

            return $Clone;
        }

        public function getBody(): StreamInterface
        {
            return $this->body;
        }

        public function withBody(StreamInterface $body): MessageInterface
        {
            $Clone = clone $this;
            $Clone->body = $body;

            return $Clone;
        }

        public function getStatusCode(): int
        {
            return $this->statusCode;
        }

        public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
        {
            $Clone = clone $this;
            $Clone->statusCode = $code;
            $Clone->reasonPhrase = $reasonPhrase;

            return $Clone;
        }

        public function getReasonPhrase(): string
        {
            return $this->reasonPhrase;
        }
    }

}
