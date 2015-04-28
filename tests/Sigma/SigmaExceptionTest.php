<?php namespace Kshabazz\Tests\Sigma;

use \Kshabazz\Sigma\SigmaException;

use const
	\Kshabazz\Sigma\BAD_ROOT_ERROR,
	\Kshabazz\Sigma\ERROR;

/**
 * Class SigmaExceptionTest
 *
 * @package Kshabazz\Tests\Sigma
 */
class SigmaExceptionTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @expectedExceptionMessage unknown error
	 */
	public function test_unknown_error_code()
	{
		throw new SigmaException( -100 );
	}

	/**
	 * @expectedExceptionMessage "test"
	 */
	public function test_parsed_error_message()
	{
		throw new SigmaException( BAD_ROOT_ERROR , ['test'] );
	}

	/**
	 * @expectedExceptionMessage "test"
	 */
	public function test_error_message()
	{
		throw new SigmaException( ERROR , ['test'] );
	}
}
?>
