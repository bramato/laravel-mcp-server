<?php

namespace Bramato\LaravelMcpServer\Transport;

use Bramato\LaravelMcpServer\Mcp\Interfaces\TransportInterface;
use Illuminate\Support\Facades\Log;

class StdioTransport implements TransportInterface
{
    protected $stdin;
    protected $stdout;

    public function __construct()
    {
        $this->stdin = fopen('php://stdin', 'rb');
        $this->stdout = fopen('php://stdout', 'wb');

        if ($this->stdin === false || $this->stdout === false) {
            throw new \RuntimeException('Failed to open standard input/output streams.');
        }

        // Opzionale: Rendi stdin non bloccante se necessario per letture future
        // stream_set_blocking($this->stdin, false);
    }

    /**
     * {@inheritdoc}
     */
    public function readMessage(): ?array
    {
        $line = fgets($this->stdin);

        if ($line === false || $line === '') {
            // End of file or error
            Log::debug('StdioTransport: fgets returned false or empty, closing input.');
            $this->close(); // Chiudi input/output se stdin finisce
            return null;
        }

        $decoded = json_decode(trim($line), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('StdioTransport: Failed to decode JSON from stdin', [
                'line' => $line,
                'error' => json_last_error_msg()
            ]);
            // Invia un errore JSON-RPC per richiesta invalida?
            $this->writeMessage([
                'jsonrpc' => '2.0',
                'id' => null, // Non possiamo sapere l'ID della richiesta fallita
                'error' => [
                    'code' => -32700,
                    'message' => 'Parse error',
                    'data' => 'Invalid JSON received: ' . json_last_error_msg(),
                ]
            ]);
            return null; // Ignora la richiesta malformata
        }

        return $decoded;
    }

    /**
     * {@inheritdoc}
     */
    public function writeMessage(array $message): void
    {
        $jsonMessage = json_encode($message);

        if ($jsonMessage === false) {
            Log::error('StdioTransport: Failed to encode JSON for stdout', [
                'message' => $message,
                'error' => json_last_error_msg()
            ]);
            return;
        }

        fwrite($this->stdout, $jsonMessage . "\n");
        fflush($this->stdout); // Assicura che l'output venga inviato immediatamente
    }

    /**
     * {@inheritdoc}
     */
    public function close(): void
    {
        if (is_resource($this->stdin)) {
            fclose($this->stdin);
            $this->stdin = null;
            Log::debug('StdioTransport: Closed stdin stream.');
        }
        if (is_resource($this->stdout)) {
            // Non chiudere stdout per permettere eventuali ultimi messaggi di errore?
            // Dipende dal caso d'uso, ma spesso è meglio lasciarlo aperto
            // fclose($this->stdout);
            // $this->stdout = null;
        }
    }

    public function __destruct()
    {
        // Assicura la chiusura delle risorse quando l'oggetto viene distrutto
        $this->close();
    }
}
