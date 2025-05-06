<?php

declare(strict_types=1);

namespace TorPHP;

use TorPHP\Exception\SocketException;
use TorPHP\Model\Circuit;
use TorPHP\Model\OnionService;
use TorPHP\Model\PortMapping;

/**
 * A client for interacting with Tor via the ControlPort.
 */
class TorControlClient
{
    private const string DEFAULT_HOST = '127.0.0.1';
    private const int DEFAULT_PORT = 9051;
    private const float DEFAULT_TIMEOUT = 30.0;

    /** @var resource|false */
    private $socket = false;

    public function __construct(
        private readonly string $host = self::DEFAULT_HOST,
        private readonly int $port = self::DEFAULT_PORT,
        private readonly ?string $password = null,
        private readonly float $timeout = self::DEFAULT_TIMEOUT,
    ) {
    }

    /**
     * Establishes connection and authenticates.
     */
    public function connect(): void
    {
        $this->socket = @fsockopen($this->host, $this->port, $errorCode, $errorMessage, $this->timeout);

        if (\is_resource($this->socket) === false) {
            throw new SocketException(\sprintf(
                'Unable to connect to Tor ControlPort at %s:%d — [%d] %s',
                $this->host,
                $this->port,
                $errorCode,
                $errorMessage,
            ));
        }

        $this->authenticate();
    }

    /**
     * Authenticates against the Tor ControlPort.
     */
    private function authenticate(): void
    {
        $cmd = $this->password !== null
            ? \sprintf("AUTHENTICATE \"%s\"\r\n", addslashes($this->password))
            : "AUTHENTICATE\r\n";

        $this->write($cmd);
        $this->expectResponse('250');
    }

    /**
     * Subscribes to given control events.
     *
     * @param string[] $events
     */
    public function setEvents(array $events): void
    {
        $list = implode(' ', $events);

        $this->write(\sprintf("SETEVENTS %s\r\n", $list));
        $this->expectResponse('250');
    }

    /**
     * Signals Tor to build a new circuit (NEWNYM).
     */
    public function signalNewnym(): void
    {
        $this->write("SIGNAL NEWNYM\r\n");
        $this->expectResponse('250');
    }

    /**
     * Blocks until at least one CIRC BUILT event is received, or times out.
     */
    public function waitForCircuitBuild(int $timeoutSeconds = 15): void
    {
        $start = time();

        while (time() - $start < $timeoutSeconds) {
            $line = $this->readLine();
            if ($line !== null && preg_match('/^650 CIRC .* BUILT/', mb_trim($line))) {
                return;
            }
        }

        throw new SocketException('Timed out waiting for new circuit build');
    }

    /**
     * Closes the control session.
     */
    public function close(): void
    {
        if (\is_resource($this->socket) === true) {
            $this->write("QUIT\r\n");

            fclose($this->socket); // @phpstan-ignore-line
            $this->socket = false;
        }
    }

    /**
     * Ensures the socket is open.
     */
    private function ensureConnected(): void
    {
        if (\is_resource($this->socket) === false) {
            throw new SocketException('Control connection is not open');
        }
    }

    private function write(string $command): void
    {
        $this->ensureConnected();
        fwrite($this->socket, $command); // @phpstan-ignore-line
    }

    private function readLine(): ?string
    {
        $this->ensureConnected();
        $line = fgets($this->socket); // @phpstan-ignore-line

        return $line === false ? null : $line;
    }

    /**
     * Expects a response prefix, returns the block and throws on mismatch.
     */
    private function expectResponse(string $prefix): string
    {
        $line = $this->readLine();

        if ($line === null || str_starts_with($line, $prefix) === false) {
            throw new SocketException(\sprintf(
                'Unexpected response from Tor ControlPort: %s',
                $line ?? 'No response',
            ));
        }

        return $line;
    }

    /**
     * Reads until the given terminator line appears, returning the accumulated data.
     */
    private function readBlock(string $until): array
    {
        $buffer = [];

        while (($line = $this->readLine()) !== null) {
            if (mb_trim($line) === $until) {
                break;
            }
            $buffer[] = mb_trim($line);
        }

        if (count($buffer) === 0) {
            throw new SocketException(\sprintf(
                'No data received before %s',
                $until,
            ));
        }

        return $buffer;
    }

    /**
     * @return Circuit[]
     */
    public function getCircuits(): array
    {
        $this->write("GETINFO circuit-status\r\n");
        $lines = $this->readBlock(until: '250 OK');

        $circuits = [];

        foreach ($lines as $line) {
            // Strip any leading "250+" prefix
            $line = (string) preg_replace('/^250\+/', '', $line);

            if (str_starts_with($line, 'circuit-status=')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 4) ?: [];

            if (\count($parts) < 4) {
                continue;
            }

            [$id, $status, $pathStr, $metaStr] = $parts;

            $nodes = array_map(
                static fn (string $hop): string => mb_ltrim($hop, '$'),
                explode(',', $pathStr),
            );

            $meta = [];
            if ($metaStr !== '') {
                $pairs = preg_split('/\s+/', $metaStr) ?: [];

                foreach ($pairs as $pair) {
                    if (str_contains($pair, '=') === false) {
                        continue;
                    }

                    [$k, $v] = explode('=', $pair, 2);
                    $meta[$k] = $v;
                }
            }

            $circuits[] = new Circuit((int) $id, $status, $nodes, $meta);
        }

        return $circuits;
    }

    /**
     * Gets a Tor configuration value via GETCONF.
     */
    public function getConfigValue(string $key): string
    {
        $this->write(\sprintf("GETCONF %s\r\n", $key));
        $raw = $this->expectResponse('250');

        // Remove the "250" prefix from the response
        $cleaned = (string) preg_replace('/^250\s+/', '', $raw);
        $trimmed = mb_trim($cleaned);

        if (str_contains($trimmed, '=') === true) {
            [, $value] = explode('=', $trimmed, 2);

            return $value;
        }

        // If no '=' found, return the raw response
        return $trimmed;
    }

    /**
     * Sets one or more Tor configuration values via SETCONF.
     */
    public function setConfigValue(string $key, string $value): void
    {
        $this->write(\sprintf("SETCONF %s=%s\r\n", $key, $value));
        $this->expectResponse('250');
    }

    /**
     * Lists configured onion services.
     */
    public function listOnions(): array
    {
        $this->ensureConnected();

        $this->write("GETINFO onions/current\r\n");
        $lines = $this->readBlock(until: '250 OK');

        foreach ($lines as $line) {
            // Strip any "250-" or "250 " prefix
            $trimmed = (string) preg_replace('/^250[- ]?/', '', $line);

            if (str_starts_with($trimmed, 'onions/current=')) {
                [, $list] = explode('=', $trimmed, 2);
                return $list === '' ? [] : explode(',', $list);
            }
        }

        return [];
    }

    /**
     * Adds (or updates) an onion service.
     *
     * @param array<PortMapping|string> $portMappings PortMapping or this format ['<remotePort>,<host>:<localPort>', ...]
     * @param string|null               $keyType Either 'NEW:ED25519-V3' or a private-key blob (default to NEW:ED25519-V3)
     */
    public function addOnion(array $portMappings, ?string $keyType = null): OnionService
    {
        $this->ensureConnected();

        if ($keyType === null) {
            $keyType = 'NEW:ED25519-V3'; // Lets the node create a private key for us
        }

        $cmd = 'ADD_ONION ' . $keyType;
        foreach ($portMappings as $mapping) {
            $mappingData = $mapping instanceof PortMapping
                ? $mapping->toPortString()
                : $mapping;

            $cmd .= ' Port=' . $mappingData;
        }
        $cmd .= "\r\n";

        $this->write($cmd);
        $lines = $this->readBlock(until: '250 OK');

        $serviceId = '';
        $privateKey = '';

        foreach ($lines as $line) {
            // Strip any leading "250-" or "250 " prefix
            $trimmed = (string) preg_replace('/^250[- ]?/', '', $line);
            if (str_starts_with($trimmed, 'ServiceID=')) {
                [, $serviceId] = explode('=', $trimmed, 2);
            } elseif (str_starts_with($trimmed, 'PrivateKey=')) {
                [, $privateKey] = explode('=', $trimmed, 2);
            }
        }

        if ($serviceId === '') {
            throw new SocketException('Failed to parse ADD_ONION response: missing ServiceID');
        }

        return new OnionService(privateKey: $privateKey, serviceId: $serviceId);
    }

    /**
     * Removes an existing onion service.
     */
    public function delOnion(string $serviceId): void
    {
        $this->ensureConnected();

        $this->write(sprintf('DEL_ONION %s\r\n', $serviceId));
        $this->expectResponse('250');
    }
}
