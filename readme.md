# Composer Run

[![Build Status](https://github.com/janpecha/composer-run/workflows/Build/badge.svg)](https://github.com/janpecha/composer-run/actions)
[![Downloads this Month](https://img.shields.io/packagist/dm/janpecha/composer-run.svg)](https://packagist.org/packages/janpecha/composer-run)
[![Latest Stable Version](https://poser.pugx.org/janpecha/composer-run/v/stable)](https://github.com/janpecha/composer-run/releases)
[![License](https://img.shields.io/badge/license-New%20BSD-blue.svg)](https://github.com/janpecha/composer-run/blob/master/license.md)

Run commands from Composer packages locally, without global installation.

<a href="https://www.janpecha.cz/donate/"><img src="https://buymecoffee.intm.org/img/donate-banner.v1.svg" alt="Donate" height="100"></a>


## Installation

[Download a latest package](https://github.com/janpecha/composer-run/releases) or use [Composer](http://getcomposer.org/):

```
composer create-project janpecha/composer-run
```

Create symlink to `composer-run` in `~/.local/bin` or add directory of Composer-Run to `PATH` environment variable.

Composer-Run requires PHP 8.4 or later.


## Usage

```
composer-run <command>
composer-run <package> <binary-name> <arguments>
```

### Run binary from one package

```
composer-run phpstan/phpstan phpstan analyse
```

Installs `phpstan/phpstan` and runs `vendor/bin/phpstan analyse`.


### Run binary from multiple packages

```
composer-run phpstan/phpstan+phpstan/extension-installer phpstan analyse
```

Installs `phpstan/phpstan` and `phpstan/extension-installer` and runs `vendor/bin/phpstan analyse`.


### Run binary from multiple packages with extra packages from `composer.json`

`myproject/composer.json`

```json
{
	"extra": {
		"phpstan-extensions": [
			"phpstan/phpstan-nette",
			"phpstan/phpstan-strict-rules"
		]
	}
}
```

```
composer-run phpstan/phpstan+phpstan/extension-installer+extra:phpstan-extensions -- phpstan analyse
```

Installs `phpstan/phpstan`, `phpstan/extension-installer` and all packages from `extra.phpstan-extension` section, runs `vendor/bin/phpstan analyse` binary.


### Run binary from popular tools

#### PHPStan

```
composer-run phpstan <arguments>
```

Installs `phpstan/phpstan`, `phpstan/extension-installer` and `extra:phpstan-extensions`, runs `vendor/bin/phpstan <arguments>` binary.


## Configuration

Create configuration file `.config.php` in Composer-Run directory.

```php
<?php

return [
	// my configuration
	'tempDirectory' => '/path/to/temp',
];
```

### composerExecutable

Name of Composer executable file (or path to executable file).

Default `composer`.


### tempDirectory

Path to temp directory for package installations.

Default `<Composer-Run directory>/.tmp`.

------------------------------

License: [New BSD License](license.md)
<br>Author: Jan Pecha, https://www.janpecha.cz/
