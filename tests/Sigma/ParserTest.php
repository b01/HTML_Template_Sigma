<?php namespace Kshabazz\Sigma\Tests;

use Kshabazz\Sigma\Parser;

/**
 * Class ParserTest
 *
 * @coversDefaultClass \Kshabazz\Sigma\Parser
 * @package Kshabazz\Sigma\Tests
 */
class ParserTest extends \PHPUnit_Framework_TestCase
{
	private
		$cacheDir,
		$fixtures;

	public function setUp()
	{
		$this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
		$this->fixtures = FIXTURES_DIR . DIRECTORY_SEPARATOR;
	}

	/**
	 * @covers ::__construct
	 */
	public function test_construct()
	{
		$parser = new Parser([ 'cache_dir' => $this->cacheDir ]);
		$this->assertInstanceOf( '\\Kshabazz\\Sigma\\Parser', $parser );
	}

	/**
	 * @covers ::loadTemplateFile
	 */
	public function test_loadTemplateFile()
	{
		$parser = new Parser();
		$actual = $parser->loadTemplateFile( $this->fixtures . 'placeholder.tpl' );
		$this->assertTrue( $actual );
	}

	/**
	 * @covers ::setCacheDir
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Directory does not exists, cannot set cache directory to: "does_not_exists"
	 */
	public function test_bad_setCacheDir()
	{
		$parser = new Parser();
		$parser->setCacheDir( 'does_not_exists' );
	}

	/**
	 * @covers ::setCacheDir
	 */
	public function test_turning_cache_off_with_null_string()
	{
		$cacheDir = FIXTURES_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
		$parser = new Parser([ Parser::OPTION_CACHE_DIR => $cacheDir ]);
		// Turn off caching empty string.
		$parser->setCacheDir( '' );
		// Parse a file
		$parser->loadTemplateFile( $this->fixtures . 'placeholder.tpl' );
		$parser->setVariable('TEST_CACHE', 'testing cache is turned on.' );

		$this->assertFileNotExists( $cacheDir . DIRECTORY_SEPARATOR . 'placeholder.tpl.it' );
	}

	/**
	 * @covers ::setCacheDir
	 */
	public function test_turning_cache_off_with_null()
	{
		$parser = new Parser([ Parser::OPTION_CACHE_DIR => $this->cacheDir ]);
		// Turn off caching with NULL.
		$parser->setCacheDir( NULL );
		// Parse a file
		$parser->loadTemplateFile( $this->fixtures . 'placeholder.tpl' );
		$parser->setVariable('TEST_CACHE', 'testing cache is turned on.' );

		$this->assertFileNotExists( $this->cacheDir . DIRECTORY_SEPARATOR . 'placeholder.tpl.it' );
	}

	/**
	 * @covers ::setCacheDir
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Directory does not exists, cannot set cache directory to: "313"
	 * @expectedExceptionCode -16
	 */
	public function test_setting_cache_with_something_other_than_a_string_or_null()
	{
		$parser = new Parser([ Parser::OPTION_CACHE_DIR => $this->cacheDir ]);
		$parser->setCacheDir( 313 );
	}
}
?>