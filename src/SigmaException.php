<?php namespace Kshabazz\Sigma;

/**
 * Class SigmaException
 *
 * @package Kshabazz\Sigma
 */
class SigmaException extends \Exception
{
	private static $errorMessages = array(
		SIGMA_ERROR                 => 'unknown error',
		SIGMA_OK                    => '',
		SIGMA_TPL_NOT_FOUND         => 'Cannot read the template file \'%s\'',
		SIGMA_BLOCK_NOT_FOUND       => 'Cannot find block \'%s\'',
		SIGMA_BLOCK_DUPLICATE       => 'The name of a block must be unique within a template. Block \'%s\' found twice.',
		SIGMA_CACHE_ERROR           => 'Cannot save template file \'%s\'',
		SIGMA_UNKNOWN_OPTION        => 'Unknown option \'%s\'',
		SIGMA_PLACEHOLDER_NOT_FOUND => 'Variable placeholder \'%s\' not found',
		SIGMA_PLACEHOLDER_DUPLICATE => 'Placeholder \'%s\' should be unique, found in multiple blocks',
		SIGMA_BLOCK_EXISTS          => 'Block \'%s\' already exists',
		SIGMA_INVALID_CALLBACK      => 'Callback does not exist',
		SIGMA_CALLBACK_SYNTAX_ERROR => 'Cannot parse template function: %s',
		SIGMA_BAD_ROOT_ERROR        => 'Cannot set root to a directory that does not exists.',
		SIGMA_BAD_CACHE_ROOT_ERROR  => 'Cannot set cache root to a directory that does not exists.'
	);

	/**
	 * Returns a textual error message for an error code
	 *
	 * @param integer $code error code or another error object for code reuse
	 * @param string $data additional data to insert into message
	 * @return string error message
	 */
	static public function errorMessage( $code, $data = NULL )
	{
		if ( !\array_key_exists($code, self::$errorMessages) )
		{
			return self::$errorMessages[SIGMA_ERROR];
		}

		return ( NULL === $data )? self::$errorMessages[ $code ] : \sprintf( self::$errorMessages[$code], $data );
	}
}
?>