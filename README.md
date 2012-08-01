Getting Started With PhpSandboxBundle
=====================================

With this bundle you can run PHP Code in a sandbox or in the current environment.
Otherwise you can use it for multi-tasking purposes running child processes in background.

## Prerequisites

This version of the bundle requires Symfony 2.1.


## Installation


### Step 1: Configure the bundle

Add the following configuration to your `config.yml` specifying the full path of your php executable:

```yaml
# app/config/config.yml
gaya_php_sandbox:
    php_settings:
        binary: /usr/bin/php # on Ubuntu 12.04
```

### Step 2: Download PhpSandboxBundle using composer

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

### Step 3: Enable the bundle

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

## Examples

### Run PHP Code in the current environment

Note: sharing functions, classes and propagating errors/exceptions (like eval)

```php
<?php

class Test
{
	public $x;
}

// ... inside a controller

$sandbox = $this->container->get('gaya_php_sandbox');
$result = $sandbox->run('$test = new Test(); $text->x = 5; echo $test->x;');

echo $result; // will output 5

// or...

$result = $sandbox->run('echo intval($_SANDBOX["arg1"]) * 2;', array('arg1' => '10'));

echo $result; // 20
```

### Run PHP Code in a separate sandbox

Note: without class/functions sharing and without errors propagating

The code is executed in a separated process

```php
<?php

$variables = array('arg1' => '3');

$result = $sandbox->runStandalone('echo intval($_SERVER["arg1"]) * 2;', $variables);

echo $result; // 6
```

Another example:

```php
<?php

use Gaya\PhpSandboxBundle\Exception\PhpSandboxNotice;
use Gaya\PhpSandboxBundle\Exception\PhpSandboxWarning;
use Gaya\PhpSandboxBundle\Exception\PhpSandboxError;

// ...

try
{
	$php = '$arr = array(1, 2, 3);';
	$sandbox->runStandalone('echo $arr[100];');
}
catch (PhpSandboxNotice $e)
{
	echo "Notice occurred: " . $e->getMessage();
}
```

### Run PHP Code in background

Note: process forking, so without class/functions sharing and without errors propagating

The code is executed in a separated child process

```php
$sandbox->runInBackground
(
	'imagecopyresized(/* ... */)',
	array('arg1', 'arg2'),
	true // TRUE means "wait for child response" | FALSE don't wait
);
```