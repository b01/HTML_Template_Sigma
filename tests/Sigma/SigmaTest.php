<?php namespace Kshabazz\Sigma\Tests;

use Kshabazz\Sigma\Sigma;

/**
 * Class SigmaTest
 *
 * @coversDefaultClass \Kshabazz\Sigma\Sigma
 * @package Kshabazz\Sigma\Tests
 */
class SigmaTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers ::__construct
	 */
	public function test_construct()
	{
		$parser = new Sigma( FIXTURES_DIR,  FIXTURES_DIR );
		$this->assertInstanceOf( '\\Kshabazz\\Sigma\\Sigma', $parser );
	}

	/**
	 * @covers ::setRoot
	 */
	public function test_setRoot()
	{
		$this->markTestIncomplete('WIP: test_setRoot');
		$parser = new Sigma('', '');
	}

//	/**
//	 * @covers ::setCacheRoot
//	 * @expectedException \Kshabazz\Sigma\SigmaException
//	 * @expectedExceptionMessage Cannot set cache root to a directory that does not exists
//	 */
//	public function test_bad_setCacheRoot()
//	{
//		$parser = new Sigma( \FIXTURES_PATH,  \FIXTURES_PATH );
//		$parser->setCacheRoot( 'does_not_exists' );
//	}
}
