<?php namespace Kshabazz\Tests\Sigma;

use Kshabazz\Sigma\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{

	/**
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage Cannot set root to a directory that does not exists
	 */
	public function test_setRoot()
	{
		$parser = new Parser('', '');
	}
}
?>