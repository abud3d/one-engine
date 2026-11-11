<?php
declare(strict_types=1);

namespace OneEngine\Modules\Runtime;

final readonly class Response
{
    public function __construct(public int $status, public string $body, public array $headers = [])
    {
    }

    public function send(bool $head = false): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if (!$head) {
            echo $this->body;
        }
    }
}
