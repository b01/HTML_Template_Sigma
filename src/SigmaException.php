<?php namespace Kshabazz\Sigma;

/**
 * Class SigmaException
 *
 * @package Kshabazz\Sigma
 */
class SigmaException extends \Exception
{
	private $errorMessages = array(
		ERROR                 => 'unknown error',
		OK                    => '',
		TPL_NOT_FOUND         => 'Cannot read the template file \'%s\'',
		BLOCK_NOT_FOUND       => 'Cannot find block \'%s\'',
		BLOCK_DUPLICATE       => 'The name of a block must be unique within a template. Block \'%s\' found twice.',
		CACHE_ERROR           => 'Cannot save template file \'%s\'',
		UNKNOWN_OPTION        => 'Unknown option \'%s\'',
		PLACEHOLDER_NOT_FOUND => 'Variable placeholder \'%s\' not found',
		PLACEHOLDER_DUPLICATE => 'Placeholder \'%s\' should be unique, found in multiple blocks',
		BLOCK_EXISTS          => 'Block \'%s\' already exists',
		INVALID_CALLBACK      => 'Callback does not exist',
		CALLBACK_SYNTAX_ERROR => 'Cannot parse template function: %s',
		BAD_ROOT_ERROR        => 'Cannot set root to a directory that does not exists "%s".',
		BAD_CACHE_ROOT_ERROR  => 'Cannot set cache root to a directory that does not exists.'
	);

	/**
	 * Construct
	 *
	 * @param string $pCode
	 * @param array $pData
	 */
	public function __construct( $pCode, array $pData = NULL )
	{
		$message = $this->getMessageByCode($pCode, $pData );
		parent::__construct($message, $pCode);
	}

	/**
	 * Returns a textual error message for an error code
	 *
	 * @param integer $code error code or another error object for code reuse
	 * @param array $data additional data to insert into message, prosessed by vsprintf()
	 * @return string error message
	 */
	public function getMessageByCode( $code, array $data = NULL )
	{
		// Return a generic error message when no entry for code found.
		if ( !\array_key_exists($code, $this->errorMessages) )
		{
			return $this->errorMessages[ERROR];
		}

		// Parse variables in the error message when present.
		if ( \is_array($data) )
		{
			return \vsprintf( $this->errorMessages[$code], $data );
		}

		return $this->errorMessages[ $code ];
	}
}
?>