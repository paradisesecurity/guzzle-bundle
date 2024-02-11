<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

class LogMessage
{
    protected string $level;

    protected LogRequest $request;

    protected LogResponse $response;

    protected null|float $transferTime;

    protected null|string $curlCommand = null;

    public function __construct(protected string $message)
    {
    }

    public function setLevel(string $level): void
    {
        $this->level = $level;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setRequest(LogRequest $value): void
    {
        $this->request = $value;
    }

    public function getRequest(): LogRequest
    {
        return $this->request;
    }

    public function setResponse(LogResponse $value)
    {
        $this->response = $value;
    }

    public function getResponse(): LogResponse
    {
        return $this->response;
    }

    public function getTransferTime(): ?float
    {
        return $this->transferTime;
    }

    public function setTransferTime(?float $transferTime): void
    {
        $this->transferTime = $transferTime;
    }

    public function getCurlCommand(): ?string
    {
        return $this->curlCommand;
    }

    public function setCurlCommand(?string $curlCommand): void
    {
        $this->curlCommand = $curlCommand;
    }
}
