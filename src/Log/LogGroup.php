<?php

declare(strict_types=1);

namespace ParadiseSecurity\Bundle\GuzzleBundle\Log;

class LogGroup
{
    protected array $messages = [];

    protected string $requestName;

    public function setRequestName(string $value): void
    {
        $this->requestName = $value;
    }

    public function getRequestName(): ?string
    {
        return $this->requestName;
    }

    public function setMessages(array $value): void
    {
        $this->messages = $value;
    }

    public function addMessages(array $value): void
    {
        $this->messages = array_merge($this->messages, $value);
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}
