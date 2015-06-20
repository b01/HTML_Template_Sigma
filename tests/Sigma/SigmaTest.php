<?php namespace Kshabazz\Sigma\Tests;

use Kshabazz\Sigma\Sigma;
use const Kshabazz\Sigma\Tests\FIXTURES_DIR;

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
	 * @covers ::setTemplateDirectory
	 */
	public function test_setRoot()
	{
		$parser = new Sigma( '', '' );
		$parser->setTemplateDirectory( FIXTURES_DIR );
		$actual = $parser->loadTemplateFile( 'placeholder.tpl' );
		$this->assertTrue( $actual );

	}

	/**
	 * @covers ::setCacheRoot
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Directory does not exists, cannot set cache directory to: "does_not_exists"
	 */
	public function test_bad_setCacheRoot()
	{
		$parser = new Sigma( FIXTURES_DIR,  FIXTURES_DIR );
		$parser->setCacheRoot( 'does_not_exists' );
	}

	/**
	 * @covers ::setCacheRoot
	 */
	public function test_turning_cache_off_with_null_string()
	{
		$cacheDir = FIXTURES_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
		$parser = new Sigma( FIXTURES_DIR, $cacheDir );
		// Turn off caching empty string.
		$parser->setCacheRoot( '' );
		// Parse a file
		$parser->loadTemplateFile( 'placeholder.tpl' );
		$parser->setVariable('TEST_CACHE', 'testing cache is turned on.' );

		$this->assertFileNotExists( $cacheDir . DIRECTORY_SEPARATOR . 'placeholder.tpl.it' );
	}

	/**
	 * @covers ::setCacheRoot
	 */
	public function test_turning_cache_off_with_null()
	{
		$cacheDir = FIXTURES_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
		$parser = new Sigma( FIXTURES_DIR, $cacheDir );
		// Turn off caching with NULL.
		$parser->setCacheRoot( NULL );
		// Parse a file
		$parser->loadTemplateFile( 'placeholder.tpl' );
		$parser->setVariable('TEST_CACHE', 'testing cache is turned on.' );

		$this->assertFileNotExists( $cacheDir . DIRECTORY_SEPARATOR . 'placeholder.tpl.it' );
	}

	/**
	 * @covers ::setCacheRoot
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Argument passed to Kshabazz\Sigma\Sigma::setCacheRoot() was invalid
	 * @expectedExceptionCode -17
	 */
	public function test_setting_cache_with_something_other_than_a_string_or_null()
	{
		$cacheDir = FIXTURES_DIR . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'cache';
		$parser = new Sigma( FIXTURES_DIR, $cacheDir );
		// Turn off caching with NULL.
		$parser->setCacheRoot( 313 );
	}
}
?>