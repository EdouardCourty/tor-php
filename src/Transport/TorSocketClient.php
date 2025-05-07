<?php

declare(strict_types=1);

namespace TorPHP\Transport;

use TorPHP\Exception\SocketException;

/**
 * A socket-based transport interface using fsockopen.
 *
 * @author Edouard Courty
 */
class TorSocketClient
{
    /** @var resource|false */
    private $socket = false;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly float $timeout,
    ) {
    }

    public function connect(): void
    {
        $socket = @fsockopen(
            $this->host,
            $this->port,
            $errorCode,
            $errorMessage,
            $this->timeout,
        );

        if (\is_resource($socket) === false) {
            throw new SocketException(
                message: \sprintf('Unable to connect to %s:%d — [%d] %s',
                    $this->host,
                    $this->port,
                    $errorCode,
                    $errorMessage,
                ),
            );
        }

        $this->socket = $socket;
    }

    public function write(string $data): void
    {
        $this->ensureConnected();
        $bytes = fwrite($this->socket, $data); // @phpstan-ignore-line (False check already done in ::ensureConnected)

        if ($bytes === false) {
            throw new SocketException(message: 'Failed to write to socket');
        }
    }

    public function readLine(): ?string
    {
        $this->ensureConnected();
        $line = fgets($this->socket); // @phpstan-ignore-line (False check already done in ::ensureConnected)

        if ($line === false) {
            return null;
        }

        return $line;
    }

    /**
     * @return string[]
     */
    public function readBlock(string $until): array
    {
        $this->ensureConnected();
        $buffer = [];

        while (true) {
            $line = $this->readLine();
            if ($line === null) {
                break;
            }

            $trimmed = mb_trim($line);
            if ($trimmed === $until) {
                break;
            }

            $buffer[] = $trimmed;
        }

        if (\count($buffer) === 0) {
            throw new SocketException(message: \sprintf(
                'No data received before %s',
                $until,
            ));
        }

        return $buffer;
    }

    public function close(): void
    {
        if (\is_resource($this->socket) === true) {
            fclose($this->socket);
        }

        $this->socket = false;
    }

    private function ensureConnected(): void
    {
        if (\is_resource($this->socket) === false) {
            throw new SocketException(message: 'Socket is not connected');
        }
    }
}
