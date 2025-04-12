<?php

namespace Bramato\LaravelMcpServer\Mcp\Exceptions;

use Exception;
use Throwable;

class McpError extends Exception
{
    /**
     * Optional data associated with the error.
     *
     * @var mixed|null
     */
    protected mixed $data;

    /**
     * Constructor.
     *
     * @param string $message Error message.
     * @param int $code Error code (JSON-RPC compatible if possible).
     * @param mixed|null $data Optional additional data.
     * @param Throwable|null $previous Previous throwable used for the exception chaining.
     */
    public function __construct(string $message = "", int $code = 0, mixed $data = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->data = $data;
    }

    /**
     * Get the optional data associated with the error.
     *
     * @return mixed|null
     */
    public function getData(): mixed
    {
        return $this->data;
    }
}
