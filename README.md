# TOR-PHP

[![PHP CI](https://github.com/EdouardCourty/tor-php/actions/workflows/php_ci.yml/badge.svg)](https://github.com/EdouardCourty/tor-php/actions/workflows/php_ci.yml)

**Tor-PHP** is a PHP library that provides two main things:

- `TorHttpClient`  
  A Tor-proxied HTTP client built on top of Symfony's `HttpClient` component.  
  _It allows you to send HTTP requests through the Tor network for anonymous browsing_
- `TorControlClient`  
  A socket client implementing the TorControl protocol to manage your node, circuits and hidden services.

---

## 🚀 Features

- HTTP requests through Tor (SOCKS5 proxy)
- Tor ControlPort integration for:
   - Requesting a new identity
   - Managing Tor circuits and configuration
   - Creating and deleting Onion Services

---

## 📦 Installation

### 1. Install Tor

You must have the Tor service installed and running.

#### On Debian/Ubuntu

```bash
sudo apt install tor
```

#### On macOS (Homebrew)

```bash
brew install tor
```

> Make sure the `ControlPort` is enabled in your Tor configuration (`/etc/tor/torrc` or equivalent):
>
> ```ini
> ControlPort 9051
> HashedControlPassword <your_password_hash>
> CookieAuthentication 0
> ```

### 2. Install Tor-PHP via Composer

```bash
composer require ecourty/tor-php
```

---

## 🛠 Requirements

- PHP 8.4 or higher
- Tor must be running locally with ControlPort enabled for full features integration

---

## 📘 Usage Examples

### Example 1: Get current IP via Tor

```php
<?php

use TorPHP\TorHttpClient;
use Symfony\Component\HttpClient\HttpClient;

$torClient = new TorHttpClient();

$response = $torClient->request('GET', 'https://api.ipify.org?format=json');
$torIp = $response->toArray()['ip'];

$normalClient = HttpClient::create();
$normalIp = $normalClient->request('GET', 'https://api.ipify.org?format=json')->toArray()['ip'];

echo "Tor IP: $torIp" . PHP_EOL;
echo "Non-Tor IP: $normalIp" . PHP_EOL;
```

---

### Example 2: Request a New Identity

```php
<?php

use TorPHP\TorHttpClient;

$torClient = new TorHttpClient();
$torClient->request('GET', 'https://example.com');

// Change circuit when you need
$torClient->newIdentity();

$response = $torClient->request('GET', 'https://example.com/another-page');
```

---

### Example 3: Manage Your Tor Node

```php
<?php

use TorPHP\TorControlClient;
use TorPHP\Model\PortMapping;

$control = new TorControlClient();

// Get circuits
$circuits = $control->getCircuits();

// Change config
$control->setConfigValue('SocksPort', '9080');

// Add onion service
$onion = $control->addOnionService([
    new PortMapping(host: 'localhost', localPort: 3000, remotePort: 80),
]);

// List onion services
$services = $control->listOnionServices();

// Delete onion service
$control->deleteOnionService($onion->id);
```

Other code examples can be [found here](./examples).

---

## 📚 Changelog

See [CHANGELOG.md](./CHANGELOG.md) for details.

---

## 👤 Author

[**Edouard Courty**](https://github.com/EdouardCourty)  
MIT Licensed [(see)](./LICENSE)  
&copy; 2025

---

## 🙋‍♂️ Need Help?

Feel free to open an issue or contribute via pull requests! <br />
[See contributing guidelines](./CONTRIBUTING.md)
