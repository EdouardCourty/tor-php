<?php

namespace TorPHP\Model;

class PortMapping
{
    public function __construct(
        public string $host,
        public int $localPort,
        public int $remotePort,
    ) {
    }

    public function toPortString(): string
    {
        return sprintf('%d,%s:%d', $this->remotePort, $this->host, $this->localPort);
    }
}
