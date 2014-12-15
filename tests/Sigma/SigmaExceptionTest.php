<?php namespace Kshabazz\Tests\Sigma;

use
	\Kshabazz\Sigma\Parser,
	\Kshabazz\Sigma\SigmaException;

/**
 * Class SigmaExceptionTest
 *
 * @package Kshabazz\Tests\Sigma
 */
class SigmaExceptionTest extends \PHPUnit_Framework_TestCase
{
	public function test_unknown_error_code()
	{
		$parser = new Parser( \FIXTURES_PATH,  \FIXTURES_PATH );
		$message = SigmaException::errorMessage( -100 );
		$this->assertTrue( strcmp('unknown error', $message) > -1 );
	}
}
?>
