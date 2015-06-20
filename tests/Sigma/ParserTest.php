<?php namespace Kshabazz\Sigma\Tests;

use Kshabazz\Sigma\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
	private
		$cache,
		$fixtures;

	public function setUp()
	{
		$this->cache = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR;
		$this->fixtures = FIXTURES_DIR;
	}
	public function test_construct()
	{
		$parser = new Parser( $this->fixtures . 'block.tpl', $this->cache );
		$this->assertInstanceOf( '\\Kshabazz\\Sigma\\Parser', $parser );
	}
}
?>