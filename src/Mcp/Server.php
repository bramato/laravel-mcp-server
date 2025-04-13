<?php

namespace Bramato\LaravelMcpServer\Mcp;

use Bramato\LaravelMcpServer\Mcp\Exceptions\McpError;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\TransportInterface;
use Sajya\Server\Http\Parser;
use Sajya\Server\Http\Request as SajyaRequest;
use Throwable;
use Illuminate\Support\Facades\Log;
use Sajya\Server\Exceptions\ParseErrorException;
use Sajya\Server\Exceptions\InvalidRequestException;
use Sajya\Server\Exceptions\InvalidParams;
use RuntimeException;

class Server implements ServerInterface
{
    protected ?TransportInterface $transport = null;
    protected array $resources = [];
    protected array $tools = [];

    /**
     * No longer needs Sajya\Server\App, but we might need other Sajya components later.
     */
    public function __construct()
    {
        Log::debug('MCP Server initialized.');
        // Registration happens via registerResource/registerTool
    }

    /**
     * Implementation of the MCP 'initialize' method.
     *
     * @return array
     */
    public function initialize(): array
    {
        $resourceUris = [];
        foreach ($this->resources as $resource) {
            $resourceUris[] = $resource->getUri();
        }

        $toolDetails = [];
        foreach ($this->tools as $name => $tool) {
            $toolDetails[$name] = [
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return [
            'capabilities' => [
                'resources' => $resourceUris,
                'tools' => $toolDetails,
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * {@inheritdoc}
     */
    public function registerResource(ResourceInterface $resource): void
    {
        // Use URI as key for easy lookup
        $this->resources[$resource->getUri()] = $resource;
        Log::debug('Registered MCP Resource: ' . $resource->getUri());
    }

    /**
     * {@inheritdoc}
     */
    public function registerTool(ToolInterface $tool): void
    {
        $toolName = $tool->getName();
        // Use name as key for easy lookup
        $this->tools[$toolName] = $tool;
        Log::debug('Registered MCP Tool: ' . $toolName);
    }

    /**
     * {@inheritdoc}
     */
    public function run(): void
    {
        if (!$this->transport) {
            throw new \RuntimeException('Transport not set before running the server.');
        }

        Log::info('MCP Server running...');

        while (($requestDataArray = $this->transport->readMessage()) !== null) {
            // Sajya's Parser expects the raw JSON string
            $requestJson = json_encode($requestDataArray);
            Log::debug('Received MCP request JSON', ['request_json' => $requestJson]);

            $responseArray = null;
            try {
                // Use Sajya's Parser to handle request structure validation and batching
                $parser = new Parser($requestJson);

                if ($parser->isError()) {
                    // Parser detected a JSON formatting error
                    $responseArray = $this->createJsonRpcErrorResponse(null, -32700, 'Parse error');
                } else {
                    $sajyaRequests = $parser->makeRequests(); // Returns an array of SajyaRequest or Exception

                    // MCP doesn't explicitly support batch requests in its core spec,
                    // but we handle it here by processing each request individually.
                    $responses = [];
                    foreach ($sajyaRequests as $request) {
                        if ($request instanceof SajyaRequest) {
                            $responses[] = $this->handleSingleRequest($request);
                        } elseif ($request instanceof Throwable) {
                            // Handle errors detected by Sajya's validation (InvalidRequest, InvalidParams)
                            $responses[] = $this->createJsonRpcErrorResponse(
                                null, // ID might not be available if request structure is invalid
                                $this->mapSajyaExceptionToCode($request),
                                $request->getMessage()
                            );
                        }
                    }

                    // If the original request was not a batch, return the single response.
                    // If it was a batch, return the array of responses.
                    // If it was a notification (no ID), handleSingleRequest returns null.
                    $responseArray = $parser->isBatch() ? $responses : ($responses[0] ?? null);

                    // Filter out null responses (notifications) from batch responses
                    if (is_array($responseArray)) {
                        $responseArray = array_filter($responseArray, fn($r) => $r !== null);
                        // If batch only contained notifications, responseArray is empty, which is correct.
                    }
                }
            } catch (Throwable $e) {
                Log::error('Unhandled error during MCP request processing: ' . $e->getMessage(), [
                    'exception' => $e,
                    'request_json' => $requestJson,
                ]);
                // Use a generic server error, trying to grab ID if possible
                $id = isset($requestDataArray['id']) ? $requestDataArray['id'] : null;
                $responseArray = $this->createJsonRpcErrorResponse($id, -32000, 'Server error: ' . $e->getMessage());
            }

            // Send response(s) if not null or empty array
            if ($responseArray !== null && $responseArray !== []) {
                Log::debug('Sending MCP response', ['response' => $responseArray]);
                $this->transport->writeMessage($responseArray); // Transport expects array
            }
        }

        Log::info('MCP Server stopped.');
        $this->transport->close();
    }

    /**
     * Handles a single raw JSON-RPC request string.
     * Primarily used by transports that handle one request at a time (like HTTP).
     *
     * @param string $requestJson The raw JSON-RPC request string.
     * @param mixed|null $connectionContext Optional context (currently unused).
     * @return string|null The raw JSON-RPC response string, or null for notifications/errors during parsing.
     */
    public function handleRequest(string $requestJson, mixed $connectionContext = null): ?string
    {
        Log::debug('Handling single MCP request JSON', ['request_json' => $requestJson]);
        $responseArray = null;
        try {
            $parser = new Parser($requestJson);

            if ($parser->isError()) {
                $responseArray = $this->createJsonRpcErrorResponse(null, -32700, 'Parse error');
            } elseif ($parser->isBatch()) {
                // MCP over HTTP typically doesn't use batch, but if it does, return an error.
                // Or, could potentially handle batch here as well, mirroring run(), but simpler to disallow.
                $responseArray = $this->createJsonRpcErrorResponse(null, -32600, 'Batch requests not supported via single handleRequest.');
            } else {
                $sajyaRequests = $parser->makeRequests(); // Should contain one request or exception
                $request = $sajyaRequests[0] ?? null;

                if ($request instanceof SajyaRequest) {
                    $responseArray = $this->handleSingleRequest($request); // This returns array or null
                } elseif ($request instanceof Throwable) {
                    // Handle parsing/validation errors from Sajya
                    $responseArray = $this->createJsonRpcErrorResponse(
                        null, // ID might not be available
                        $this->mapSajyaExceptionToCode($request),
                        $request->getMessage()
                    );
                } else {
                    // Should not happen with non-batch request
                    $responseArray = $this->createJsonRpcErrorResponse(null, -32600, 'Invalid request format.');
                }
            }
        } catch (Throwable $e) {
            Log::error('Unhandled error during single MCP request handling: ' . $e->getMessage(), [
                'exception' => $e,
                'request_json' => $requestJson,
            ]);
            // Attempt to get ID from raw json if possible for error response
            $decoded = json_decode($requestJson, true);
            $id = isset($decoded['id']) && !is_array($decoded) ? $decoded['id'] : null;
            $responseArray = $this->createJsonRpcErrorResponse($id, -32000, 'Server error: ' . $e->getMessage());
        }

        if ($responseArray === null) {
            return null; // It was a notification or an error occurred before parsing ID
        }

        // Return the JSON string representation
        $responseJson = json_encode($responseArray);
        Log::debug('Sending single MCP response JSON', ['response_json' => $responseJson]);
        return $responseJson;
    }

    /**
     * Handles a single parsed Sajya JSON-RPC request object.
     * (Logic moved from previous handleRequest)
     *
     * @param SajyaRequest $request The parsed Sajya request object.
     * @return array|null The JSON-RPC response array, or null for notifications.
     */
    protected function handleSingleRequest(SajyaRequest $request): ?array
    {
        $method = $request->getMethod();
        $params = $request->getParams() ?? []; // Ensure params is an array
        $id = $request->getId();

        try {
            // 1. Handle 'initialize'
            if ($method === 'initialize') {
                // Allow params to be null or an empty array, but not contain actual values.
                if ($params !== null && $params !== []) {
                    throw new InvalidParams("The 'initialize' method does not accept parameters.");
                }
                $result = $this->initialize();
                return $this->createJsonRpcResultResponse($id, $result);
            }

            // 2. Handle 'tool:*' methods
            if (str_starts_with($method, 'tool:')) {
                $toolName = substr($method, 5);
                if (!isset($this->tools[$toolName])) {
                    throw new McpError("Tool '$toolName' not found.", -32601);
                }
                $tool = $this->tools[$toolName];
                // TODO: Parameter validation for tools
                Log::debug("Executing tool '$toolName'", ['params' => $params]);
                $result = $tool->execute($params);
                return $this->createJsonRpcResultResponse($id, $result);
            }

            // 3. Handle 'resource:*' methods
            if (str_starts_with($method, 'resource:')) {
                $resourceUri = substr($method, 9);
                if (!isset($this->resources[$resourceUri])) {
                    throw new McpError("Resource '$resourceUri' not found.", -32601, ['uri' => $resourceUri]);
                }
                $resource = $this->resources[$resourceUri];
                // Allow params to be null or an empty array, but not contain actual values.
                if ($params !== null && $params !== []) {
                    throw new InvalidParams("Method 'resource:$resourceUri' does not accept parameters.");
                }
                Log::debug("Getting contents for resource '$resourceUri'");
                $result = $resource->getContents();
                return $this->createJsonRpcResultResponse($id, $result);
            }

            // 4. Unknown method
            throw new McpError("Method '$method' not found.", -32601);
        } catch (Throwable $e) {
            Log::warning("Error handling MCP method '$method': " . $e->getMessage(), ['exception' => $e]);

            $errorCode = -32000;
            if ($e instanceof InvalidParams) {
                $errorCode = -32602;
            } elseif ($e instanceof McpError) {
                $errorCode = $e->getCode();
            } elseif ($e instanceof InvalidRequestException) {
                $errorCode = -32600;
            } elseif ($e instanceof ParseErrorException) {
                $errorCode = -32700;
            }

            return $this->createJsonRpcErrorResponse($id, $errorCode, $e->getMessage(), $e instanceof McpError ? $e->getData() : null);
        }
    }

    /**
     * Maps Sajya Exception types and McpError to JSON-RPC error codes.
     */
    protected function mapSajyaExceptionToCode(Throwable $e): int
    {
        if ($e instanceof ParseErrorException) return -32700;
        if ($e instanceof InvalidRequestException) return -32600;
        // MethodNotFound is now handled within handleSingleRequest using McpError
        if ($e instanceof InvalidParams) return -32602;
        if ($e instanceof McpError) return $e->getCode(); // Use code from McpError
        return -32000; // Default Server Error
    }


    /**
     * Helper to create a JSON-RPC success response array.
     */
    protected function createJsonRpcResultResponse($id, mixed $result): ?array
    {
        // Notifications have no ID and expect no response
        if ($id === null) {
            return null;
        }
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * Helper to create a JSON-RPC error response array.
     */
    protected function createJsonRpcErrorResponse($id, int $code, string $message, mixed $data = null): array
    {
        // Error responses MUST have an ID if the request had one, otherwise ID can be null.
        // Notifications (null ID) technically shouldn't get error responses according to spec,
        // but we might generate one if parsing/validation fails before we know it's a notification.
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }
        return [
            'jsonrpc' => '2.0',
            'id' => $id, // Can be null if request ID was null or couldn't be parsed
            'error' => $error,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getResources(): array
    {
        return $this->resources;
    }

    /**
     * {@inheritdoc}
     */
    public function getTools(): array
    {
        return $this->tools;
    }
}
