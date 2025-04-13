<?php

use Illuminate\Support\Facades\Route;
use Bramato\LaravelMcpServer\Http\Controllers\RpcController;
use Illuminate\Http\Response;

// Prendiamo il path dalla configurazione, con un default
$path = config('mcp.transports.http.path', '/mcp-rpc');

// Definiamo la route solo se il path non è vuoto
if (!empty($path)) {
    // Handle JSON-RPC calls via POST
    Route::post($path, [RpcController::class, 'handle'])
        ->name('mcp.rpc.http.post');

    // Add a GET route for SSE Transport / compatibility with MCP clients
    Route::get($path, function () {
        // Impostiamo immediatamente gli header per SSE
        $response = new Response();
        $response->header('Content-Type', 'text/event-stream');
        $response->header('Cache-Control', 'no-cache');
        $response->header('Connection', 'keep-alive');
        $response->header('X-Accel-Buffering', 'no');

        // Forziamo l'invio degli header prima di iniziare lo streaming
        $response->sendHeaders();

        // Inviamo il messaggio iniziale
        echo "data: " . json_encode([
            'type' => 'connection',
            'status' => 'ok',
            'message' => 'Laravel MCP Server HTTP SSE Transport active.'
        ]) . "\n\n";

        ob_flush();
        flush();

        // Keep the connection open
        while (true) {
            // Check connection status
            if (connection_aborted()) {
                break;
            }

            // Send heartbeat every 30 seconds
            sleep(30);
            echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
            ob_flush();
            flush();
        }

        return $response;
    })->name('mcp.rpc.http.get');
}
