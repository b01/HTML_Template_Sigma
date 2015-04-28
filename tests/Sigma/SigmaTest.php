<?php namespace Kshabazz\Sigma\Tests;

use Kshabazz\Sigma\Sigma;

/**
 * Class SigmaTest
 *
 * @package Kshabazz\Sigma\Tests
 */
class SigmaTest extends \PHPUnit_Framework_TestCase
{

	public function test_setRoot()
	{
		$parser = new Sigma( \FIXTURES_PATH,  \FIXTURES_PATH );
		$this->assertInstanceOf( '\\Kshabazz\\Sigma\\Sigma', $parser );
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Cannot set root to a directory that does not exists
	 */
	public function test_bad_setRoot()
	{
		$parser = new Sigma('', '');
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Cannot set cache root to a directory that does not exists
	 */
	public function test_bad_setCacheRoot()
	{
		$parser = new Sigma( \FIXTURES_PATH,  \FIXTURES_PATH );
		$parser->setCacheRoot( 'does_not_exists' );
	}
}
