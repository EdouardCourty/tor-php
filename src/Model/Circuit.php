<?php

declare(strict_types=1);

namespace TorPHP\Model;

/**
 * Represents a Tor circuit.
 *
 * @author Edouard Courty
 */
class Circuit
{
    /**
     * @param array<string>         $nodes
     * @param array<string, string> $metadata
     */
    public function __construct(
        public int $id,
        public string $status,
        public array $nodes,
        public array $metadata,
    ) {
    }
}
