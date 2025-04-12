<?php

use Illuminate\Support\Facades\Route;
use Bramato\LaravelMcpServer\Http\Controllers\RpcController;

// Prendiamo il path dalla configurazione, con un default
$path = config('mcp.transports.http.path', '/mcp-rpc');

// Definiamo la route solo se il path non è vuoto
if (!empty($path)) {
    Route::post($path, [RpcController::class, 'handle'])
        ->name('mcp.rpc.http'); // Nome opzionale per la route
}
