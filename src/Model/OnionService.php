<?php

declare(strict_types=1);

namespace TorPHP\Model;

class OnionService
{
    public function __construct(
        public string $id,
        public string $url,
        public string $privateKey,
    ) {
    }
}
