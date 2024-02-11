<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

use ParadiseSecurity\Bundle\GuzzleBundle\ParadiseSecurityGuzzleBundle;
use Psr\Http\Message\ResponseInterface;

class LogResponse
{
    protected int $statusCode;

    protected string $statusPhrase;

    protected string $body;

    protected array $headers = [];

    protected string $protocolVersion;

    public function __construct(ResponseInterface $response, private bool $logBody = true)
    {
        $this->save($response);
    }

    public function save(ResponseInterface $response): void
    {
        $this->setStatusCode($response->getStatusCode());
        $this->setStatusPhrase($response->getReasonPhrase());

        $this->setHeaders($response->getHeaders());
        $this->setProtocolVersion($response->getProtocolVersion());

        if ($this->logBody) {
            $this->setBody($response->getBody()->getContents());

            // rewind to previous position after reading response body
            if ($response->getBody()->isSeekable()) {
                $response->getBody()->rewind();
            }

            return;
        }

        $this->setBody(ParadiseSecurityGuzzleBundle::class . ': [response body log disabled]');
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $value): void
    {
        $this->statusCode = $value;
    }

    public function getStatusPhrase(): string
    {
        return $this->statusPhrase;
    }

    public function setStatusPhrase(string $value): void
    {
        $this->statusPhrase = $value;
    }

    public function getBody()
    {
        return $this->body;
    }

    public function setBody(string $value): void
    {
        $this->body = $value;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function setProtocolVersion(string $value): void
    {
        $this->protocolVersion = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $value): void
    {
        $this->headers = $value;
    }
}
