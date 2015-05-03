<?php namespace Kshabazz\Sigma;


const
	OK = 1,
	ERROR = -1,
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
	BAD_ROOT_ERROR = -15,
	BAD_CACHE_ROOT_ERROR = -16;
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
		ERROR = -1,
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
		BAD_ROOT = -15,
		BAD_CACHE_ROOT_ERROR = -16;
	/**#@-*/

	private $errorMessages = array(
		self::ERROR                 => 'unknown error',
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
		self::BAD_ROOT              => 'Cannot set root to a directory that does not exists "%s".',
		self::BAD_CACHE_ROOT_ERROR  => 'Cannot set cache root to a directory that does not exists.'
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