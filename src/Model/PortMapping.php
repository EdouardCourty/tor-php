<?php

declare(strict_types=1);

namespace TorPHP\Model;

class PortMapping
{
    public function __construct(
        public string $host,
        public int $localPort,
        public int $remotePort,
    ) {
    }

    public function toString(): string
    {
        return \sprintf('%d,%s:%d', $this->remotePort, $this->host, $this->localPort);
    }

    public static function fromString(string $payload): self
    {
        if (preg_match('/^(\d+),([\w\.\-]+):(\d+)$/', mb_trim($payload), $matches) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid port mapping format: "%s"', $payload));
        }

        [, $remotePort, $host, $localPort] = $matches;

        return new self(
            host: $host,
            localPort: (int) $localPort,
            remotePort: (int) $remotePort,
        );
    }
}
