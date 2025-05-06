<?php

declare(strict_types=1);

namespace TorPHP\Model;

class Circuit
{
    /**
     * @param array<string>         $paths
     * @param array<string, string> $meta
     */
    public function __construct(
        public int $id,
        public string $status,
        public array $paths,
        public array $meta,
    ) {
    }
}
