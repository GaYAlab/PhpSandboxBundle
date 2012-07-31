Getting Started With PhpSandboxBundle
=====================================

With this bundle you can run PHP Code in a sandbox or in the current environment.
Otherwise you can use it for multi-tasking purposes running child processes in background.

## Prerequisites

This version of the bundle requires Symfony 2.1.


## Installation


### Step 1: Download PhpSandboxBundle using composer

Add PhpSandboxBundle in your composer.json:

```js
"require": {
	"gayalab/phpsandboxbundle": "*"
}
```

Now tell composer to download the bundle by running the command:

```bash
$ php composer.phar update gayalab/phpsandboxbundle
```

Composer will install the bundle to your project's `vendor/gayalab` directory.

### Step 2: Enable the bundle

Enable the bundle in the kernel:

```php
<?php
// app/AppKernel.php

public function registerBundles()
{
	$bundles = array(
		// ...
		new Gaya\PhpSandboxBundle\GayaPhpSandboxBundle(),
	);
}
```

### Step 3: Configure the bundle

Add the following configuration to your `config.yml` specifying the full path of your php executable:

```yaml
# app/config/config.yml
gaya_php_sandbox:
    php_settings:
        binary: /usr/bin/php # on Ubuntu 12.04
```

## Examples

### Run PHP Code in the current environment (sharing functions, classes and propagating errors/exceptions)

```php
class Test
{
	public $x;
}

// ... inside a controller

$sandbox = $this->container->get('gaya_php_sandbox');
$sandbox->run('$test = new Test(); $text->x = 5;');

echo $test->x; // will output 5

// or...

$result = $sandbox->run('echo 3 * 2;');

echo $result; // 6
```