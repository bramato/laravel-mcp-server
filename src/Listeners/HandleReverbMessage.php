<?php

namespace Bramato\LaravelMcpServer\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Reverb\Events\MessageReceived;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Throwable;

class HandleReverbMessage // implements ShouldQueue // Considerare se renderlo accodabile
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct(protected ServerInterface $server)
    {
        // Inietta il server MCP
    }

    /**
     * Handle the event.
     *
     * @param  MessageReceived  $event
     * @return void
     */
    public function handle(MessageReceived $event): void
    {
        $connectionId = $event->connection->id();
        $messageJson = $event->message;

        Log::debug('MCP Reverb Transport: Received message', [
            'connectionId' => $connectionId,
            'message' => json_decode($messageJson, true) // Logga decodificato
        ]);

        // Verifica base se è JSON valido (handleRequest farà controlli più profondi)
        $requestData = json_decode($messageJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::warning('MCP Reverb Transport: Received invalid JSON', [
                'connectionId' => $connectionId,
                'message' => $messageJson,
                'error' => json_last_error_msg()
            ]);
            // Inviare un errore JSON-RPC indietro?
            $errorJson = json_encode([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32700, 'message' => 'Parse error'],
                'id' => null
            ]);
            try {
                $event->connection->send($errorJson);
            } catch (Throwable $e) {
                Log::error('MCP Reverb Transport: Failed to send parse error response', ['error' => $e->getMessage()]);
            }
            return;
        }

        try {
            // Passa il JSON GREZZO e l'ID connessione al server handler
            $responseJson = $this->server->handleRequest($messageJson, $connectionId);

            if ($responseJson !== null) {
                Log::debug('MCP Reverb Transport: Sending response', [
                    'connectionId' => $connectionId,
                    'response' => json_decode($responseJson, true) // Logga decodificato
                ]);
                // Invia la risposta JSON GREZZA solo al client mittente
                $event->connection->send($responseJson);
            }
        } catch (Throwable $e) {
            Log::error('MCP Reverb Transport: Error handling request', [
                'connectionId' => $connectionId,
                'message' => $requestData, // Logga l'array decodificato qui
                'exception' => $e
            ]);
            // Tentare di inviare un errore JSON-RPC generico?
            // handleRequest/Sajya dovrebbero aver già gestito errori interni
            $errorJson = json_encode([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32000, 'message' => 'Internal server error'],
                'id' => $requestData['id'] ?? null // Prova a recuperare l'ID dalla richiesta decodificata
            ]);
            try {
                $event->connection->send($errorJson);
            } catch (Throwable $sendError) {
                Log::error('MCP Reverb Transport: Failed to send internal error response', ['error' => $sendError->getMessage()]);
            }
        }
    }
}
