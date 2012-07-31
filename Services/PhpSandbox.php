<?php

namespace Gaya\PhpSandboxBundle\Services;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Gaya\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

/**
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandbox
{
	private $container;
	private $phpBinary;
	private $phpLastCode;

	const CODE_SNIPPET_ERROR_REPORTING = "ini_set('display_errors', '1'); error_reporting(E_ALL);";
	const CODE_SNIPPET_HARAKIRI = 'unlink(__FILE__);';

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
		$this->phpBinary = $this->container->getParameter('gaya.php_settings.binary');
	}

	/**
	 * Returns the Symfony2 cache dir
	 *
	 * @return string Symfony2 cache dir
	 */
	private function getCacheDir()
	{
		return $this->container->get('kernel')->getCacheDir();
	}

	/**
	 * Returns the PHP Sandbox script dir
	 *
	 * @return string PHP Sandbox script dir
	 */
	public function getPhpSandboxDir()
	{
		return $this->getCacheDir() . '/gaya_php_sandbox';
	}

	/**
	 * Create the sandbox directory in which put the new php scripts
	 * The sandbox directory will be created inside the Symfony2 cache dir
	 *
	 * @throws \Exception
	 */
	private function mkdir()
	{
		$dir = $this->getPhpSandboxDir();

		if (realpath($dir) === false)
		{
			mkdir($dir, 0777, true);

			if (realpath($dir) === false)
				throw new \Exception('Unable to create the PHP Sandbox directory');
		}
	}

	/**
	 * Get an unique token to unique filename purposes
	 *
	 * @param string $salt If not specified microtime() will be used
	 * @return string Unique token
	 */
	private function getUniqueToken($salt = null)
	{
		if (!$salt)
			$salt = microtime();

		return md5($salt . uniqid());
	}

	/**
	 * Prepare the PHP code adding the start tag <?php if it doesn't exists
	 * and adding error_reporting or harakiri option
	 *
	 * WARNING: short_open_tag is not allowed: use <?php or leave empty
	 *
	 * @param string $code PHP Code
	 * @param boolean $error_reporting Enable the error_reporting(E_ALL)
	 * @param boolean $harakiri If TRUE the script will self-destruct MWUAH UAH UAH
	 * @return string PHP Code with starting tag <?php
	 */
	private function preparePhpCode($code, $error_reporting = false, $harakiri = false)
	{
		$errInstruction = self::CODE_SNIPPET_ERROR_REPORTING;
		$err = $error_reporting ? $errInstruction : '';

		if (strpos($code, '<?php') === false)
			$code = '<?php ' . ($err != '' ? trim($err) . ' ' : '') . trim($code);
		else if (($pos = strpos($code, '<?php')) !== false)
		{
			$pos += 5;
			$code = trim( substr($code, 0, $pos) )
					. ($error_reporting ? " $errInstruction " : ' ')
					. trim( substr($code, $pos) );
		}

		if ($harakiri)
		{
			$harakiri = self::CODE_SNIPPET_HARAKIRI;

			if (($pos = strpos($code, '?>')) !== false)
				$code = trim(substr($code, 0, $pos)) . " $harakiri " . trim(substr($code, $pos));
			else
				$code = trim($code) . " $harakiri";
		}

		return $code;
	}

	/**
	 * Returns the last PHP code executed
	 *
	 * @return string $phpCode
	 */
	public function getLastPhpCode()
	{
		return $this->phpLastCode;
	}

	/**
	 * Run the PHP Code in the current environment
	 * So classes and functions available in your script are available in the new PHP code
	 *
	 * @param string $code PHP Code
	 * @param array $variables Array of variables to pass at new PHP script
	 * @return string Script output
	 * @throws \Exception
	 */
	public function run($code, $variables = array())
	{
		$_SANDBOX = $variables;
		unset($variables);

		$_PhpSandboxFullPath = $this->getPhpSandboxDir() . DIRECTORY_SEPARATOR . $this->getUniqueToken($code) . '.php';

		$this->mkdir();
		$this->phpLastCode = $this->preparePhpCode($code);
		file_put_contents($_PhpSandboxFullPath, $this->phpLastCode);

		$_PhpSandboxBufferBackup = null;

		if (ob_get_length())
		{
			$_PhpSandboxBufferBackup = ob_get_contents();
			ob_end_clean();
		}

		ob_start();

		try
		{
			include_once($_PhpSandboxFullPath);
		}
		catch (\Exception $e)
		{
			unlink($_PhpSandboxFullPath);
			throw $e;
		}

		$_PhpSandBoxResult = ob_get_contents();
		ob_end_clean();

		if ($_PhpSandboxBufferBackup)
		{
			ob_start();
			echo $_PhpSandboxBufferBackup;
		}

		unlink($_PhpSandboxFullPath);

		return $_PhpSandBoxResult;
	}

	/**
	 * Run the PHP Code in a standalone process
	 * So classes and functions available in your script are NOT available in the new PHP code
	 *
	 * @param string $code PHP Code
	 * @param array $variables Array of variables to pass at new PHP script
	 * @return string Script output
	 * @throws \Exception
	 */
	public function runStandalone($code, $variables = array())
	{
		$stream = null;
		$wdir = $this->getPhpSandboxDir();
		$token = $this->getUniqueToken($code);

		$errorFile = $wdir . DIRECTORY_SEPARATOR . $token . '.log';

		$descriptorSpec = array
		(
			0 => array('pipe', 'r'),
			1 => array('pipe', 'w'),
			2 => array('file', $errorFile, 'a')
		);

		$this->mkdir();
		$process = proc_open($this->phpBinary, $descriptorSpec, $pipes, $wdir, $variables);

		if (is_resource($process))
		{
			$this->phpLastCode = $this->preparePhpCode($code, true);

			fwrite($pipes[0], $this->phpLastCode);
			fclose($pipes[0]);

			$stream = stream_get_contents($pipes[1]);
			fclose($pipes[1]);

			proc_close($process);
		}

		if (realpath($errorFile) !== false && filesize($errorFile))
		{
			$errorLog = file_get_contents($errorFile);
			unlink($errorFile);
			ErrorHandler::checkErrorLog($errorLog);
		}

		if (realpath($errorFile))
			unlink($errorFile);

		return $stream;
	}

	/**
	 * Run the PHP Code in a standalone process and in background
	 * So classes and functions available in your script are NOT available in the new PHP code
	 * and the parent script will not wait the child response
	 *
	 * @param string $code PHP Code
	 * @param array $variables Array of variables to pass at new PHP script
	 * @param boolean $debug If TRUE the parent script wait for the child execution printing his output
	 * @throws \Exception If is not possibile to fork the PHP process
	 */
	public function runInBackground($code, $variables = array(), $debug = false)
	{
		$_PhpSandboxFullPath = $this->getPhpSandboxDir() . DIRECTORY_SEPARATOR . $this->getUniqueToken($code) . '.php';

		$this->mkdir();
		$this->phpLastCode = $this->preparePhpCode($code, false, true);
		file_put_contents($_PhpSandboxFullPath, $this->phpLastCode);

		$pid = pcntl_fork();

		if ($pid == -1)
			throw new \Exception('Could not fork');
		else if ($pid)
		{
			if ($debug)
				pcntl_waitpid($pid, $status);
		}
		else
			pcntl_exec($this->phpBinary, array($_PhpSandboxFullPath), $variables);
	}
}