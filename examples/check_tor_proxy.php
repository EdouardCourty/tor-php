<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use TorPHP\TorHttpClient;

$torHttpClient = new TorHttpClient();
$response = $torHttpClient->request('GET', 'http://check.torproject.org/api/ip')->toArray();

if ($response['IsTor'] === true) {
    echo 'You are using Tor.' . \PHP_EOL;
} else {
    echo 'You are not using Tor.' . \PHP_EOL;
}
