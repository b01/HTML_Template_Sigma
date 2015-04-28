<?php namespace Kshabazz\Tests\Sigma;

use Kshabazz\Sigma\Parser;
use Kshabazz\Sigma\SigmaException;

class ParserTest extends \PHPUnit_Framework_TestCase
{
	public function test_setRoot()
	{
		$parser = new Parser( \FIXTURES_PATH,  \FIXTURES_PATH );
		$this->assertInstanceOf( '\\Kshabazz\\Sigma\\Parser', $parser );
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Cannot set root to a directory that does not exists
	 */
	public function test_bad_setRoot()
	{
		$parser = new Parser('', '');
	}

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Cannot set cache root to a directory that does not exists
	 */
	public function test_bad_setCacheRoot()
	{
		$parser = new Parser( \FIXTURES_PATH,  \FIXTURES_PATH );
		$parser->setCacheRoot( 'does_not_exists' );
	}
}
?>