<?php

namespace Gaya\PhpSandboxBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

use Gaya\PhpSandboxBundle\Services\PhpSandbox;

/**
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class CacheClearCommand extends ContainerAwareCommand
{
	protected function configure()
	{
		$this
			->setName('gaya:phpsandbox:cache:clear')
			->setDescription('This command clears the script temporary folder')
		;
	}

	private function rmdirr($dir, $base = false)
	{
		if (($dir = realpath($dir)) && ($objs = glob($dir . DIRECTORY_SEPARATOR . '*')))
			foreach($objs as $obj)
				is_dir($obj) ? $this->rmdirr($obj, $base === false ? $dir : $base) : unlink($obj);

		if ($base && $base != $dir)
			return rmdir($dir);
		else
		{
			$files = glob($dir . DIRECTORY_SEPARATOR . '*');

			if (!$files || (is_array($files) && !$files))
				return true;
		}

		return false;
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$sandbox = new PhpSandbox($this->getContainer());

		if ( !$this->rmdirr($sandbox->getPhpSandboxDir()) )
			$output->writeln('<info>Cache cleared</info>');
		else
			$output->writeln('<error>Unable to clear the cache folder (' . $sandbox->getPhpSandboxDir() . ')</error>');
	}
}