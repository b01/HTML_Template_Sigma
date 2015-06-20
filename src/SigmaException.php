<?php namespace Kshabazz\Sigma;

/**
 * Class SigmaException
 *
 * @package Kshabazz\Sigma
 */
class SigmaException extends \Exception
{
	/**#@+
	 * Error codes
	 * @see HTML_Template_Sigma::errorMessage()
	 */
	const
		GENERIC = -1,
		TPL_NOT_FOUND = -2,
		BLOCK_NOT_FOUND = -3,
		BLOCK_DUPLICATE = -4,
		CACHE_ERROR = -5,
		UNKNOWN_OPTION = -6,
		PLACEHOLDER_NOT_FOUND = -10,
		PLACEHOLDER_DUPLICATE = -11,
		BLOCK_EXISTS = -12,
		INVALID_CALLBACK = -13,
		CALLBACK_SYNTAX_ERROR = -14,
		BAD_TEMPLATE = -15,
		BAD_CACHE_DIR = -16;
	/**#@-*/

	private $errorMessages = [
		self::GENERIC               => 'unknown error',
		self::TPL_NOT_FOUND         => 'Cannot read the template file \'%s\'',
		self::BLOCK_NOT_FOUND       => 'Cannot find block \'%s\'',
		self::BLOCK_DUPLICATE       => 'The name of a block must be unique within a template. Block \'%s\' found twice.',
		self::CACHE_ERROR           => 'Cannot save template file \'%s\'',
		self::UNKNOWN_OPTION        => 'Unknown option \'%s\'',
		self::PLACEHOLDER_NOT_FOUND => 'Variable placeholder \'%s\' not found',
		self::PLACEHOLDER_DUPLICATE => 'Placeholder \'%s\' should be unique, found in multiple blocks',
		self::BLOCK_EXISTS          => 'Block \'%s\' already exists',
		self::INVALID_CALLBACK      => 'Callback does not exist',
		self::CALLBACK_SYNTAX_ERROR => 'Cannot parse template function: %s',
		self::BAD_TEMPLATE          => 'The template file does not exists: "%s".',
		self::BAD_CACHE_DIR         => 'Cannot set cache root to a directory that does not exists "%s".'
	];

	/**
	 * Construct
	 *
	 * @param string $pCode
	 * @param array $pData
	 * @param string $customMessage Override built-in messages with your own, will also be used for custom error codes.
	 */
	public function __construct( $pCode, array $pData = NULL, $customMessage = NULL )
	{
		// When custom message is anything other than a string or null.
		if ( !(is_string($customMessage) && strlen($customMessage) > 0) && !is_null($customMessage) )
		{
			throw new \InvalidArgumentException(
				'Must be a string of length greater than zero or null'
			);
		}
		$message = $this->getMessageByCode( $pCode, $pData, $customMessage );
		parent::__construct( $message, $pCode );
	}

	/**
	 * Returns a textual error message for an error code
	 *
	 * @param integer $code error code or another error object for code reuse
	 * @param array $data additional data to insert into message, processed by vsprintf()
	 * @param string $customMessage Override the built-in message with your own.
	 * @return string error message
	 */
	public function getMessageByCode( $code, array $data = NULL, $customMessage = NULL )
	{
		// Use custom message when set.
		if ( !empty($customMessage) && strlen($customMessage) > 0 )
		{
			return \vsprintf($customMessage, $data );
		}

		// When no entry for code found, return a generic error message.
		if ( !\array_key_exists($code, $this->errorMessages) )
		{
			return $this->errorMessages[ self::GENERIC ];
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