<?php namespace Kshabazz\Tests\Sigma;

use \Kshabazz\Sigma\SigmaException;

/**
 * Class SigmaExceptionTest
 *
 * @package Kshabazz\Tests\Sigma
 */
class SigmaExceptionTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage unknown error
	 */
	public function test_unknown_error_code()
	{
		throw new SigmaException( -100 );
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage "test"
	 */
	public function test_parsed_error_message()
	{
		throw new SigmaException( SigmaException::BAD_ROOT , ['test'] );
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage unknown error
	 */
	public function test_error_message()
	{
		throw new SigmaException( SigmaException::ERROR , ['test'] );
	}
}
?>
