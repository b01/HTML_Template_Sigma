<?php namespace Kshabazz\Sigma\Tests\Handlers;

use \Kshabazz\Sigma\Handlers\Block;

use const \Kshabazz\Sigma\Tests\FIXTURES_DIR;

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
		$blocks = new Block( '',  '{', '}' );
		$this->assertInstanceOf('Kshabazz\\Sigma\\Handlers\\Block', $blocks );
	}

	/**
	 * @covers ::getBlockList
	 * @covers ::buildBlocks
	 */
	public function test_getBlockList()
	{
		$file = FIXTURES_DIR . DIRECTORY_SEPARATOR . 'block.tpl';
		$template = \file_get_contents( $file );
		$blocks = new Block( $template );

		$blockList = $blocks->getBlockList();

		$this->assertEquals( 'TEST_1', $blockList[0] );
	}

//	/**
//	 * @covers ::addBlock
//	 * @expectedException \Kshabazz\Sigma\SigmaException
//	 * @expectedExceptionMessage Variable placeholder 'PLACEHOLDER_0' not found
//	 * @expectedExceptionCode -10
//	 */
//	public function test_call_addBlock_with_non_existing_placeholder()
//	{
//		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'block.tpl';
//		$template = \file_get_contents( $file );
//		$blocks = new Block( $template, '{', '}' );
//		$blocks->addBlock( 'PLACEHOLDER_0', 'TEST_4', 'test 4 content' );
//	}
//
//	/**
//	 * @covers ::addBlock
//	 */
//	public function test_call_addBlock_with_bad_format()
//	{
//		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'block.tpl';
//		$template = \file_get_contents( $file );
//		$blocks = new Block( $template, '{', '}' );
//		$template = '{PLACEHOLDER_1}';
//		$blocks->addBlock( 'PLACEHOLDER_1', '<!-- BEGIN TEST_4 -->test 4 content<!-- END TEST_4 -->', $template );
//		$blockList = $blocks->getBlockList();
//
//		$this->assertEquals( 'TEST_4', $blockList[0] );
//	}
}
?>