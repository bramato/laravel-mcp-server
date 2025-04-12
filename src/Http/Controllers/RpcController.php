<?php

namespace Bramato\LaravelMcpServer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;
use Illuminate\Support\Facades\Log;

class RpcController extends Controller
{
    /**
     * Handle incoming JSON-RPC requests over HTTP.
     *
     * @param Request $request
     * @param ServerInterface $server
     * @return Response
     */
    public function handle(Request $request, ServerInterface $server): Response
    {
        $requestJson = $request->getContent();

        if (empty($requestJson)) {
            Log::warning('MCP HTTP Transport: Received empty request body.');
            // Risposta JSON-RPC per "Invalid Request" secondo la specifica
            return response(
                json_encode([
                    'jsonrpc' => '2.0',
                    'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                    'id' => null
                ]),
                400, // Bad Request
                ['Content-Type' => 'application/json']
            );
        }

        Log::debug('MCP HTTP Transport: Received request', ['body' => json_decode($requestJson, true)]);

        // Delega la gestione al Server, che a sua volta usa Sajya
        // handleRequest si aspetta e restituisce una stringa JSON grezza
        $responseJson = $server->handleRequest($requestJson);

        // Se la risposta è null (es. notifica JSON-RPC che non richiede risposta su HTTP),
        // restituiamo HTTP 204 No Content.
        // Se la risposta non è null, la restituiamo come JSON.
        if ($responseJson === null) {
            Log::debug('MCP HTTP Transport: Sending HTTP 204 No Content response for notification.');
            return response()->noContent();
        }

        Log::debug('MCP HTTP Transport: Sending response', ['body' => json_decode($responseJson, true)]);
        return response(
            $responseJson,
            200,
            ['Content-Type' => 'application/json']
        );
    }
}
