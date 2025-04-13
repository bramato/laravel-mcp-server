<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Bramato\LaravelMcpServer\Http\Controllers\RpcController;

// Prendiamo il path dalla configurazione, con un default
$path = config('mcp.transports.http.path', '/mcp-rpc');

// Definiamo la route solo se il path non è vuoto
if (!empty($path)) {
    // Handle JSON-RPC calls via POST
    Route::post($path, [RpcController::class, 'handle'])
        ->name('mcp.rpc.http.post');

    // Add a GET route for SSE Transport / compatibility with MCP clients
    Route::get($path, function (Request $request) {
        // Impostiamo gli header per SSE
        $response = response()->stream(
            function () {
                // Send connection initialization message according to MCP protocol
                echo "data: " . json_encode([
                    "jsonrpc" => "2.0",
                    "method" => "initialize",
                    "params" => [
                        "protocolVersion" => "2.0",
                        "serverInfo" => [
                            "name" => "Laravel MCP Server",
                            "version" => "1.0.0"
                        ],
                        "capabilities" => [
                            "resources" => [],
                            "tools" => []
                        ]
                    ]
                ]) . "\n\n";
                ob_flush();
                flush();

                // After initialization, wait for client requests
                $lastPingTime = time();

                while (true) {
                    // Check connection status
                    if (connection_aborted()) {
                        break;
                    }

                    // Send heartbeat notification every 30 seconds
                    if (time() - $lastPingTime >= 30) {
                        echo "data: " . json_encode([
                            "jsonrpc" => "2.0",
                            "method" => "ping",
                            "params" => [
                                "timestamp" => time()
                            ]
                        ]) . "\n\n";

                        $lastPingTime = time();
                        ob_flush();
                        flush();
                    }

                    // Small sleep to prevent CPU usage
                    usleep(100000); // 100ms
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no'
            ]
        );

        return $response;
    })->name('mcp.rpc.http.get');
}
