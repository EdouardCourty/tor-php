# TOR-PHP Changelog

This file contains information about every addition, update and deletion in the `ecourty/tor-php` library.  
It is recommended to read this file before updating the library to a new version.

## v1.0.0

Initial release of the project.

#### Additions

- Added the [`TorPHP\TorHttpClient`](./src/TorHttpClient.php) to handle Tor-proxied HTTP requests.
  - Implements `Symfony\Contracts\HttpClient\HttpClientInterface` for easy integration with other projects.
  - Integrates [`TorPHP\TorControlClient`](./src/TorControlClient.php) to handle Tor circuit change.

- Added the [`TorPHP\TorControlClient`](./src/TorControlClient.php) to handle TorControl commands.
  - Integrates [`TorPHP\Transport\TorSocketClient`](./src/Transport/TorSocketClient.php) to handle TorControl socket connections.
  - Supports the following:
    - Gather current node's circuits
    - Get / Set configuration options
    - List current Onion services hosted on the node
    - Creating / Deleting Onion services
  - Handles authentication with password or cookie (or none).

- Added the [`TorPHP\Transport\TorSocketClient`](./src/Transport/TorSocketClient.php) to handle TorControl socket connections.
- Added unit tests under [tests](./tests)
- Added code examples under [examples](./examples)
