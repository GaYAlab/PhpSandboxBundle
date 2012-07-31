Getting Started With PhpSandboxBundle
=====================================

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

```
$ php composer.phar update gayalab/phpsandboxbundle
```

Composer will install the bundle to your project's `vendor/gayalab` directory.

### Step 2: Enable the bundle

Enable the bundle in the kernel:

```
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

Add the following configuration to your `config.yml` specifying the full path of your php executable.

```
# app/config/config.yml
gaya_php_sandbox:
    php_settings:
        binary: /usr/bin/php # on Ubuntu 12.04
```