<?php

use Illuminate\Support\Facades\Route;
use Bramato\LaravelMcpServer\Http\Controllers\RpcController;

// Prendiamo il path dalla configurazione, con un default
$path = config('mcp.transports.http.path', '/mcp-rpc');

// Definiamo la route solo se il path non è vuoto
if (!empty($path)) {
    // Handle JSON-RPC calls via POST
    Route::post($path, [RpcController::class, 'handle'])
        ->name('mcp.rpc.http.post');

    // Add a GET route for SSE Transport / compatibility with MCP clients
    Route::get($path, function () {
        return response()->stream(
            function () {
                echo "data: " . json_encode([
                    'type' => 'connection',
                    'status' => 'ok',
                    'message' => 'Laravel MCP Server HTTP SSE Transport active. Ready for MCP communication.'
                ]) . "\n\n";

                ob_flush();
                flush();

                // Keep the connection open
                while (true) {
                    // Check connection status
                    if (connection_aborted()) {
                        break;
                    }

                    // Send heartbeat every 30 seconds to keep connection alive
                    sleep(30);
                    echo "data: " . json_encode(['type' => 'heartbeat']) . "\n\n";
                    ob_flush();
                    flush();
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no', // Disable nginx buffering
            ]
        );
    })->name('mcp.rpc.http.get');
}
