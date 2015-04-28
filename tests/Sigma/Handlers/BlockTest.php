<?php namespace Kshabazz\Sigma\Tests\Handlers;

use Kshabazz\Sigma\Handlers\Block;

/**
 * Class BlockTest
 *
 * @package Kshabazz\Sigma\Tests\Handlers
 */
class BlockTest extends \PHPUnit_Framework_TestCase
{
	public function setUp()
	{
	}

	public function test_construct()
	{
		$file = FIXTURES_PATH . DIRECTORY_SEPARATOR . 'block.tpl';
		$template = file_get_contents( $file );
		$blocks = new Block( $template, '{', '}' );

		$blockList = $blocks->getBlockList();

		var_dump($blockList);
	}
}
?>