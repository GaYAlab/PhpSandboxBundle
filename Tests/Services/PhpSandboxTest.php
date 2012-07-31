<?php

namespace Gaya\PhpSandboxBundle\Tests\Services;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

use Gaya\PhpSandboxBundle\Services\PhpSandbox;
use Gaya\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

/**
 * @author Francesco Casula <fra.casula@gmail.com>
 */
class PhpSandboxTest extends WebTestCase
{
	private $phpSandbox;

	public function setUp()
	{
		$this->phpSandbox = new PhpSandbox( static::createClient()->getContainer() );
	}

	private function folderIsEmpty($folder = null)
	{
		if (!$folder)
			$folder = $this->phpSandbox->getPhpSandboxDir();

		if (($folder = realpath($folder)) && is_dir($folder))
		{
			if ($handle = opendir($folder))
			{
				while ( ($file = readdir($handle)) !== false )
				{
					if ( ($file = realpath($folder . DIRECTORY_SEPARATOR . $file)) && is_file($file) )
					{
						closedir($handle);
						return false;
					}
				}

				closedir($handle);
			}
		}

		return true;
	}

	private function assertPhpSandboxDirIsEmpty()
	{
		$this->assertTrue( $this->folderIsEmpty(), 'PHP Sandbox dir is not empty' );
	}

    public function testService()
    {
        $client = static::createClient();
        $container = $client->getContainer();

		$phpSandbox = $container->get('gaya_php_sandbox');

		$this->assertTrue( $phpSandbox instanceof PhpSandbox );
    }

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::mkdir
	 */
	public function testMkDir()
	{
		$this->phpSandbox->run('echo 1;');

		$this->assertTrue( realpath( $this->phpSandbox->getPhpSandboxDir() ) !== false );
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::preparePhpCode
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::getLastPhpCode
	 */
	public function testPreparePhpCode()
	{
		$code = array
		(
			'1' => '$x = null;',
			'2' => '<?php $x = null;',
			'3' => '<?php $x = null; ?>',
		);

		$expected = array
		(
			array
			(
				'method' => 'run',
				'code' => '1',
				'expected' => '<?php $x = null;'
			),
			array
			(
				'method' => 'run',
				'code' => '2',
				'expected' => '<?php $x = null;'
			),
			array
			(
				'method' => 'run',
				'code' => '3',
				'expected' => '<?php $x = null; ?>'
			),
			array
			(
				'method' => 'runStandalone',
				'code' => '1',
				'expected' => '<?php ' . PhpSandbox::CODE_SNIPPET_ERROR_REPORTING . ' $x = null;'
			),
			array
			(
				'method' => 'runStandalone',
				'code' => '2',
				'expected' => '<?php ' . PhpSandbox::CODE_SNIPPET_ERROR_REPORTING . ' $x = null;'
			),
			array
			(
				'method' => 'runStandalone',
				'code' => '3',
				'expected' => '<?php ' . PhpSandbox::CODE_SNIPPET_ERROR_REPORTING . ' $x = null; ?>'
			),
		);

		foreach ($expected as $test)
		{
			$method = $test['method'];
			$this->phpSandbox->$method($code[$test['code']]);
			$this->assertEquals($test['expected'], $this->phpSandbox->getLastPhpCode());
		}

		$this->assertPhpSandboxDirIsEmpty();

		$expected = array
		(
			array
			(
				'method' => 'runInBackground',
				'code' => '1',
				'expected' => '<?php $x = null; ' . PhpSandbox::CODE_SNIPPET_HARAKIRI
			),
			array
			(
				'method' => 'runInBackground',
				'code' => '2',
				'expected' => '<?php $x = null; ' . PhpSandbox::CODE_SNIPPET_HARAKIRI
			),
			array
			(
				'method' => 'runInBackground',
				'code' => '3',
				'expected' => '<?php $x = null; ' . PhpSandbox::CODE_SNIPPET_HARAKIRI . ' ?>'
			),
		);

		foreach ($expected as $test)
		{
			$method = $test['method'];
			$this->phpSandbox->$method($code[$test['code']]);
			$this->assertEquals($test['expected'], $this->phpSandbox->getLastPhpCode());
		}

		$time = time();
		$timeout = 10;
		$assertion = false;

		while (true)
		{
			if ($this->folderIsEmpty())
				break;

			if ((time() - $time) > $timeout)
				break;
		}

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::run
	 */
	public function testRunEcho()
	{
		$this->expectOutputString('6');

		echo $this->phpSandbox->run('echo 3 * 2;');

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::run
	 */
	public function testRunClassReference()
	{
		$this->expectOutputString('123');

		$php =
<<<PHP
class Test
{
	public \$x;
}

\$one = new Test();
\$two= \$one;

\$two->x = 123;

echo \$one->x;
PHP;

		echo $this->phpSandbox->run($php);

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::run
	 */
	public function testRunException()
	{
		try
		{
			$this->phpSandbox->run('throw new \Exception();');
		}
		catch (\Exception $e)
		{
			$this->assertTrue($e instanceof \Exception);
			$this->assertPhpSandboxDirIsEmpty();
		}
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::run
	 */
	public function testRunNamespace()
	{
		$php =
<<<PHP
namespace MyApp\MyTest\MyPackage {
	class Test
	{
		public function printTest(\$value) { echo \$value; }
	}
}

namespace MyApp\MyTest\Test {
	use MyApp\MyTest\MyPackage\Test;

	\$x = new Test();
	\$x->printTest('first test');
}
PHP;

		$this->assertEquals( 'first test', $this->phpSandbox->run($php) );

		$expectedString = 'second value';

		$this->expectOutputString($expectedString);

		$test = new \MyApp\MyTest\MyPackage\Test();
		$test->printTest($expectedString);

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandalone()
	{
		$variables = array
		(
			'arg1' => '3',
			'arg2' => '6',
			'arg3' => '9',
		);

		$php =
<<<PHP
\$arg1 = (int) \$_SERVER['arg1'];
\$arg2 = (int) \$_SERVER['arg2'];
\$arg3 = (int) \$_SERVER['arg3'];

echo (\$arg1 * \$arg2 * \$arg3);
PHP;

		$res = $this->phpSandbox->runStandalone($php, $variables);

		$this->assertEquals( $res, '162' );

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @expectedException \Gaya\PhpSandboxBundle\Exception\PhpSandboxNotice
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandaloneNotice()
	{
		$this->phpSandbox->runStandalone('echo $x[0];');
		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @expectedException \Gaya\PhpSandboxBundle\Exception\PhpSandboxWarning
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandaloneWarning()
	{
		$this->phpSandbox->runStandalone('include("file_that_doesnt_exists");');
		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandaloneParseError()
	{
		$this->setExpectedException('\Gaya\PhpSandboxBundle\Exception\PhpSandboxError', '', ErrorHandler::PHP_PARSE_ERROR);

		$this->phpSandbox->runStandalone('x x x');
		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandaloneFatalError()
	{
		$this->setExpectedException('\Gaya\PhpSandboxBundle\Exception\PhpSandboxError', '', ErrorHandler::PHP_FATAL_ERROR);

		$this->phpSandbox->runStandalone('call_to_undefined_function();');
		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runStandalone
	 */
	public function testRunStandaloneIsReallyStandalone()
	{
		$this->setExpectedException('\Gaya\PhpSandboxBundle\Exception\PhpSandboxError', '', ErrorHandler::PHP_FATAL_ERROR);

		$php =
<<<PHP
use Gaya\PhpSandboxBundle\Services\PhpSandboxErrorHandler as ErrorHandler;

ErrorHandler::checkErrorLog('fakeLog');
PHP;

		$this->phpSandbox->runStandalone($php);

		$this->assertPhpSandboxDirIsEmpty();
	}

	/**
	 * @covers \Gaya\PhpSandboxBundle\Services\PhpSandbox::runInBackground
	 */
	public function testRunInBackground()
	{
		$writeDir = $this->phpSandbox->getPhpSandboxDir();

		$php =
<<<PHP
\$index = (int) \$_SERVER['index'];
\$writeDir = trim(\$_SERVER['writeDir']);

\$filename = \$writeDir . DIRECTORY_SEPARATOR . "testRunInBackground_\$index.txt";

file_put_contents(\$filename, (string) \$index);
PHP;

		for ( $i = 0; $i < 10; $i++ )
			$this->phpSandbox->runInBackground($php, array('index' => $i, 'writeDir' => $writeDir), false);

		$time = time();
		$timeout = 10;
		$assertion = false;

		while (true)
		{
			$check = true;

			for ( $i = 0; $i < 10; $i++ )
				if (!realpath($writeDir . DIRECTORY_SEPARATOR . "testRunInBackground_$i.txt"))
					$check = false;

			if ($check)
			{
				$assertion = true;
				break;
			}
			else if ((time() - $time) > $timeout)
				break;
		}

		for ( $i = 0; $i < 10; $i++ )
			if (($filename = realpath($writeDir . DIRECTORY_SEPARATOR . "testRunInBackground_$i.txt")))
			{
				$this->assertEquals($i, file_get_contents($filename));
				unlink($filename);
			}

		$this->assertTrue($assertion, "Unable to verify background file creation in $timeout seconds");

		$this->assertPhpSandboxDirIsEmpty();
	}
}