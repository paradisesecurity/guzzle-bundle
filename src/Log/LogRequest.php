<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

use Psr\Http\Message\RequestInterface;

class LogRequest
{
    protected string $host;

    protected int|null $port;

    protected string $url;

    protected string $path;

    protected string $scheme;

    protected array $headers = [];

    protected string $protocolVersion;

    protected string $method;

    protected string|null $body;

    public function __construct(RequestInterface $request)
    {
        $this->save($request);
    }

    protected function save(RequestInterface $request): void
    {
        $uri = $request->getUri();

        $this->setHost($uri->getHost());
        $this->setPort($uri->getPort());
        $this->setUrl((string) $uri);
        $this->setPath($uri->getPath());
        $this->setScheme($uri->getScheme());
        $this->setHeaders($request->getHeaders());
        $this->setProtocolVersion($request->getProtocolVersion());
        $this->setMethod($request->getMethod());

        // rewind to previous position after logging request
        $readPosition = null;
        if ($request->getBody() && $request->getBody()->isSeekable()) {
            $readPosition = $request->getBody()->tell();
        }

        $this->setBody($request->getBody() ? $request->getBody()->__toString() : null);

        if ($readPosition !== null) {
            $request->getBody()->seek($readPosition);
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $value): void
    {
        $this->host = $value;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(?int $value): void
    {
        $this->port = $value;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $value): void
    {
        $this->url = $value;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $value): void
    {
        $this->path = $value;
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function setScheme(string $value): void
    {
        $this->scheme = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $value): void
    {
        $this->headers = $value;
    }

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function setProtocolVersion(string $value): void
    {
        $this->protocolVersion = $value;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $value): void
    {
        $this->method = $value;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $value): void
    {
        $this->body = $value;
    }
}
