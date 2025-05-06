PHP = php

PHPUNIT = vendor/bin/phpunit
PHPSTAN = vendor/bin/phpstan
PHPCSFIXER = vendor/bin/php-cs-fixer

COMPOSER = composer

install:
	$(COMPOSER) install

test:
	$(PHP) $(PHPUNIT) tests

phpstan:
	$(PHP) $(PHPSTAN) analyse --memory-limit=-1

phpcs:
	PHP_CS_FIXER_IGNORE_ENV=1 $(PHP) $(PHPCSFIXER) fix . --config .php-cs-fixer.php
