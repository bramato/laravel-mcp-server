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

    // Add a GET route for health check / compatibility with clients like Cursor
    Route::get($path, function () {
        return response()->json([
            'status' => 'ok',
            'message' => 'Laravel MCP Server HTTP endpoint is active. Use POST for JSON-RPC requests.'
        ]);
    })->name('mcp.rpc.http.get');
}
