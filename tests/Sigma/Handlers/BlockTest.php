<?php namespace Kshabazz\Sigma\Tests\Handlers;

use Kshabazz\Sigma\Handlers\Block;

/**
 * Class BlockTest
 *
 * @coversDefaultClass \Kshabazz\Sigma\Handlers\Block
 * @package Kshabazz\Sigma\Tests\Handlers
 */
class BlockTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
	}

	/**
	 * @covers ::__construct
	 */
	public function test_construct()
	{
		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'block.tpl';
		$template = \file_get_contents( $file );
		$blocks = new Block( $template, '{', '}' );

		$blockList = $blocks->getBlockList();

		$this->assertEquals( 'TEST_1', $blockList[0] );
	}

	/**
	 * @covers ::addBlock
	 */
	public function test_addBlock()
	{
		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'block.tpl';
		$template = \file_get_contents( $file );
		$blocks = new Block( $template, '{', '}' );

		$blockList = $blocks->getBlockList();

		$this->assertEquals( 'TEST_1', $blockList[0] );
	}
}
?>