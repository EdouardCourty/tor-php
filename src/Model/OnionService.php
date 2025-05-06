<?php

declare(strict_types=1);

namespace TorPHP\Model;

class OnionService
{
    public function __construct(
        public string $privateKey,
        public string $serviceId,
    ) {
    }
}
