<?php namespace Kshabazz\Tests\Sigma;

use \Kshabazz\Sigma\SigmaException;

/**
 * Class SigmaExceptionTest
 *
 * @package Kshabazz\Tests\Sigma
 * @coversDefaultClass \Kshabazz\Sigma\SigmaException
 */
class SigmaExceptionTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @covers ::__construct
	 */
	public function test_construct()
	{
		$this->assertInstanceOf(
			'\\Kshabazz\\Sigma\\SigmaException',
			new SigmaException(1)
		);
	}

	/**
	 * @covers ::getMessageByCode
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage unknown error
	 */
	public function test_unknown_error_code()
	{
		throw new SigmaException( -100 );
	}

	/**
	 * @covers ::getMessageByCode
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage "test"
	 */
	public function test_parsed_error_message()
	{
		throw new SigmaException( SigmaException::BAD_TEMPLATE_DIR , ['test'] );
	}

	/**
	 * @covers ::getMessageByCode
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionMessage unknown error
	 */
	public function test_error_message()
	{
		throw new SigmaException( SigmaException::GENERIC , ['test'] );
	}

	/**
	 * @covers ::getMessageByCode
	 */
	public function test_known_error_code_with_no_parsing()
	{
		$exception = new SigmaException( SigmaException::GENERIC );

		$message = $exception->getMessageByCode( SigmaException::GENERIC );

		$this->assertContains( 'unknown error', $message );
	}

	/**
	 * @covers ::getMessageByCode
	 * @expectedException \Kshabazz\Sigma\SigmaException
	 * @expectedExceptionCode -1000.2
	 * @expectedExceptionMessage success
	 */
	public function test_setting_a_custom_message()
	{
		throw new SigmaException(
			'-1000.1',
			['success'],
			'Test error message %s'
		);
	}

	/**
	 * @covers ::__construct
	 * @expectedException \InvalidArgumentException
	 */
	public function test_set_an_invalid_custom_message()
	{
		throw new SigmaException(
			'-1000.1',
			['success'],
			1
		);
	}
}
?>
