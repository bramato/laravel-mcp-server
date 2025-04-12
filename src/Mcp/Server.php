<?php

namespace Bramato\LaravelMcpServer\Mcp;

use Bramato\LaravelMcpServer\Mcp\Interfaces\ResourceInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ToolInterface;
use Bramato\LaravelMcpServer\Mcp\Interfaces\TransportInterface;
use Sajya\Server\Server as SajyaRpcServer;
use Throwable;
use Illuminate\Support\Facades\Log;

class Server implements ServerInterface
{
    protected ?TransportInterface $transport = null;
    protected array $resources = [];
    protected array $tools = [];

    /**
     * @param SajyaRpcServer $rpcHandler The underlying JSON-RPC handler instance.
     */
    public function __construct(protected SajyaRpcServer $rpcHandler)
    {
        // Registra la procedura MCP 'initialize'
        $this->rpcHandler->procedure('initialize', [$this, 'initialize']);
        Log::debug('Registered MCP procedure: initialize');
    }

    /**
     * Implementation of the MCP 'initialize' method.
     *
     * @return array
     */
    public function initialize(): array
    {
        return [
            'capabilities' => [
                // TODO: Estrarre le capacità effettive in modo più robusto
                'resources' => array_keys($this->resources),
                'tools' => array_keys($this->tools), // Nomi registrati nel config/provider
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
        $this->resources[$resource->getUri()] = $resource;
        // TODO: Implementare la gestione delle chiamate a 'resource:{uri}' tramite Sajya?
        // Forse con un metodo generico o una procedura dedicata?
        Log::debug('Registered MCP Resource: ' . $resource->getUri());
    }

    /**
     * {@inheritdoc}
     */
    public function registerTool(ToolInterface $tool): void
    {
        $toolName = $tool->getName();
        $this->tools[$toolName] = $tool;
        // Registra il tool come procedura callable con Sajya, prefissato con 'tool:'
        $this->rpcHandler->procedure('tool:' . $toolName, [$tool, 'execute']);
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
            // Sajya si aspetta la stringa JSON grezza
            $requestJson = json_encode($requestDataArray);
            Log::debug('Received MCP request', ['request' => $requestDataArray]); // Logga l'array per leggibilità

            $responseJson = null;
            try {
                // Delega la gestione completa a Sajya
                $responseJson = $this->handleRequest($requestJson);
            } catch (Throwable $e) {
                Log::error('Unhandled error during MCP request processing: ' . $e->getMessage(), [
                    'exception' => $e,
                    'request' => $requestDataArray,
                ]);
                // In caso di errore non gestito da Sajya, potremmo inviare un errore generico
                // Ma Sajya dovrebbe catturare la maggior parte degli errori e formattarli.
                // $errorResponse = $this->createJsonRpcErrorResponse($requestDataArray['id'] ?? null, -32000, 'Server error');
                // $this->transport->writeMessage($errorResponse);
                // continue; // Passa alla prossima richiesta
            }

            // Sajya restituisce null per le notifiche, non inviare risposta in quel caso
            if ($responseJson !== null) {
                // La risposta è già una stringa JSON
                Log::debug('Sending MCP response', ['response' => json_decode($responseJson, true)]); // Logga decodificato
                // Assumiamo che writeMessage accetta ancora un array, quindi decodifichiamo?
                // Verifichiamo TransportInterface... no, accetta array.
                // Quindi dobbiamo decodificare la risposta JSON di Sajya.
                $responseDataArray = json_decode($responseJson, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->transport->writeMessage($responseDataArray);
                } else {
                    Log::error('Failed to decode Sajya JSON response', ['response' => $responseJson, 'error' => json_last_error_msg()]);
                }
            }
        }

        Log::info('MCP Server stopped.');
        $this->transport->close();
    }

    /**
     * Handles a raw JSON-RPC request string by passing it to the Sajya handler.
     *
     * @param string $requestJson The raw JSON-RPC request string.
     * @param mixed|null $connectionContext Optional context (currently unused by Sajya handler).
     * @return string|null The raw JSON-RPC response string, or null for notifications.
     */
    public function handleRequest(string $requestJson, mixed $connectionContext = null): ?string
    {
        // Delega direttamente a Sajya, che gestisce parsing, routing, esecuzione, formattazione errori.
        return $this->rpcHandler->handle($requestJson);
    }

    /**
     * Helper per creare una risposta di errore JSON-RPC (mantenuto per possibili usi futuri).
     */
    protected function createJsonRpcErrorResponse($id, int $code, string $message, mixed $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];
        if ($data !== null) {
            $error['data'] = $data;
        }
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => $error,
        ];
    }
}
