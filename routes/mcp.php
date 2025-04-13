<?php

use Illuminate\Support\Facades\Route;
use Bramato\LaravelMcpServer\Http\Controllers\RpcController;
use Symfony\Component\HttpFoundation\StreamedResponse; // Needed for SSE

// Prendiamo il path dalla configurazione, con un default
$path = config('mcp.transports.http.path', '/mcp-rpc');

// Definiamo la route solo se il path non è vuoto
if (!empty($path)) {
    // Handle JSON-RPC calls via POST
    Route::post($path, [RpcController::class, 'handle'])
        ->name('mcp.rpc.http.post');

    // Add a GET route for health check / compatibility with clients expecting SSE
    Route::get($path, function () {
        // Return a minimal valid SSE response
        $response = new StreamedResponse(function () {
            // Send a comment to keep the connection open or signal readiness
            echo ": MCP Server Ready\n\n";
            flush();
            // Normally, you might loop here sending events, but for just satisfying
            // the content type check, a single comment might suffice.
            // If Cursor *needs* continuous events, this would need expansion.
        });
        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // Useful for Nginx proxying
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    })->name('mcp.rpc.http.get');
}
