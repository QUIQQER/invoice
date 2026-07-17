<?php

namespace QUI\REST;

use Psr\Http\Message\StreamInterface;

use function strlen;
use function substr;

class ResponseStream implements StreamInterface
{
    private string $content = '';
    private int $position = 0;

    public function __toString(): string
    {
        return $this->content;
    }

    public function close(): void
    {
    }

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->content);
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function eof(): bool
    {
        return $this->position >= strlen($this->content);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->position = $offset;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function isWritable(): bool
    {
        return true;
    }

    public function write(string $string): int
    {
        $this->content .= $string;
        $this->position = strlen($this->content);

        return strlen($string);
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $result = substr($this->content, $this->position, $length);
        $this->position += strlen($result);

        return $result;
    }

    public function getContents(): string
    {
        $result = substr($this->content, $this->position);
        $this->position = strlen($this->content);

        return $result;
    }

    public function getMetadata(?string $key = null)
    {
        return $key === null ? [] : null;
    }
}
