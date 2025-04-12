<?php

namespace Bramato\LaravelMcpServer\Tests\Feature;

use Bramato\LaravelMcpServer\Tests\TestCase;
use Bramato\LaravelMcpServer\Tests\Fixtures\DummyTool;
use Bramato\LaravelMcpServer\Mcp\Interfaces\ServerInterface;

class HttpTransportTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);
        // Assicurati che il trasporto HTTP sia "abilitato" per il test
        // e che il percorso sia quello di default
        $app['config']->set('mcp.enabled_transports', ['http']);
        $app['config']->set('mcp.transports.http.path', '/mcp-rpc');

        // Pulisci risorse/tool per i test che non li registrano
        $app['config']->set('mcp.resources', []);
        $app['config']->set('mcp.tools', []);
    }

    /** @test */
    public function initialize_lists_registered_resources_and_tools()
    {
        // Sovrascrivi la config per questo test specifico
        config([
            'mcp.resources' => [
                \Bramato\LaravelMcpServer\Tests\Fixtures\DummyResource::class,
            ],
            'mcp.tools' => [
                \Bramato\LaravelMcpServer\Tests\Fixtures\DummyTool::class,
            ]
        ]);

        // Ricrea l'istanza del server con la nuova config
        $this->app->forgetInstance(ServerInterface::class);

        $initializeRequest = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 'http-req-2',
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'id' => 'http-req-2',
            'result' => [
                'capabilities' => [
                    'resources' => ['test://dummy'], // L'URI della DummyResource
                    'tools' => ['dummyTool'],     // Il nome del DummyTool
                ]
            ]
        ];

        $response = $this->postJson('/mcp-rpc', $initializeRequest);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson($expectedResponse);
    }

    /** @test */
    public function it_handles_initialize_request_via_http()
    {
        $initializeRequest = [
            'jsonrpc' => '2.0',
            'method' => 'initialize',
            'id' => 'http-req-1',
        ];

        $expectedResponse = [
            'jsonrpc' => '2.0',
            'id' => 'http-req-1',
            'result' => [
                'capabilities' => [
                    'resources' => [],
                    'tools' => [],
                ]
            ]
        ];

        $response = $this->postJson('/mcp-rpc', $initializeRequest);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson($expectedResponse);
    }

    /** @test */
    public function it_handles_method_not_found_error_via_http()
    {
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'nonExistentMethod',
            'id' => 'http-err-1',
        ];

        $expectedErrorResponse = [
            'jsonrpc' => '2.0',
            'id' => 'http-err-1',
            'error' => [
                'code' => -32601, // Method not found
                'message' => 'Method not found',
                // Sajya potrebbe aggiungere dettagli qui, quindi non usiamo assertExactJson
            ]
        ];

        $response = $this->postJson('/mcp-rpc', $request);

        $response->assertStatus(200) // JSON-RPC errors return HTTP 200
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson($expectedErrorResponse);
        // Verifica specifici campi dell'errore
        //->assertJsonPath('error.code', -32601)
        //->assertJsonPath('error.message', 'Method not found');
    }

    /** @test */
    public function it_handles_invalid_request_via_http()
    {
        // Corpo richiesta vuoto
        $responseEmpty = $this->postJson('/mcp-rpc');
        $responseEmpty->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'jsonrpc' => '2.0',
                'error' => ['code' => -32600, 'message' => 'Invalid Request'],
                'id' => null
            ]);

        // JSON non valido
        $responseInvalidJson = $this->post('/mcp-rpc', [], ['CONTENT_TYPE' => 'application/json'])
            ->withContent('{"jsonrpc": "2.0", invalid}');
        $responseInvalidJson->assertStatus(200) // Sajya potrebbe gestire questo come errore JSON-RPC
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('error.code', -32700); // Parse error
    }

    /** @test */
    public function it_handles_notification_request_via_http()
    {
        $notificationRequest = [
            'jsonrpc' => '2.0',
            'method' => 'notifySomething', // Metodo non esistente, ma è una notifica
        ];

        // Le notifiche (senza ID) non dovrebbero generare risposta JSON,
        // il nostro RpcController restituisce HTTP 204 No Content.
        $response = $this->postJson('/mcp-rpc', $notificationRequest);

        $response->assertStatus(204); // No Content
    }

    /** @test */
    public function it_handles_tool_execution_via_http()
    {
        // Configura l'app per registrare il DummyTool
        config([
            'mcp.tools' => [
                DummyTool::class,
            ]
        ]);

        // Ricrea l'istanza del server con la nuova config (necessario?)
        // Il singleton viene risolto di nuovo per ogni test?
        // Testbench dovrebbe farlo, ma verifichiamo...
        // $this->app->forgetInstance(ServerInterface::class);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tool:dummyTool', // Nota il prefisso 'tool:' aggiunto da Server::registerTool
            'params' => [
                'param1' => 'hello',
                'param2' => 123,
            ],
            'id' => 'http-tool-1',
        ];

        $expectedResult = [
            'message' => 'Executed dummy tool',
            'received_param1' => 'hello',
            'received_param2' => 123,
        ];

        $response = $this->postJson('/mcp-rpc', $request);

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'jsonrpc',
                'id',
                'result' => [
                    'message',
                    'received_param1',
                    'received_param2'
                ]
            ])
            ->assertJsonPath('id', 'http-tool-1')
            ->assertJsonPath('result', $expectedResult);
    }

    /** @test */
    public function it_handles_tool_execution_error_via_http()
    {
        config([
            'mcp.tools' => [
                DummyTool::class,
            ]
        ]);

        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tool:dummyTool',
            // Manca 'param1' richiesto
            'params' => [
                'param2' => 456,
            ],
            'id' => 'http-tool-err-1',
        ];

        $response = $this->postJson('/mcp-rpc', $request);

        // Sajya dovrebbe catturare l'InvalidArgumentException e restituire un errore JSON-RPC
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonStructure([
                'jsonrpc',
                'id',
                'error' => ['code', 'message']
            ])
            ->assertJsonPath('id', 'http-tool-err-1')
            ->assertJsonPath('error.code', -32602); // Invalid params
        // Potrebbe anche essere -32000 (Server error) a seconda di come Sajya mappa l'eccezione
    }
}
