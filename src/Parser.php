<?php namespace Kshabazz\Sigma;
/**
 * This is the core Sigma class which processes template files by way of parsing/setting blocks, placeholders, and
 * handling of caching. This class is what a developer will generally instantiate in their code.
 *
 * Originally this was Sigma/Sigma, but has been rewritten to break out core pieces, in an effort to make it more
 * readable. In the hopes that it will be simpler to maintain and improve as time goes on.
 */

/**
 * Class Parser
 *
 * @package Kshabazz\Sigma
 */
class Parser
{
	/**
	 * RegExp for matching the block names in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var      string
	 * @access   public
	 * @see      $variablenameRegExp, $openingDelimiter, $closingDelimiter
	 */
	var $blocknameRegExp = '[0-9A-Za-z_-]+';

	/**
	 * RegExp used to find blocks and their content, filled by the constructor
	 * @var      string
	 * @see      HTML_Template_Sigma()
	 */
	var $blockRegExp = '';

	/**
	 * Template blocks and their content
	 *
	 * @var \Kshabazz\Sigma\Handlers\Block;
	 * @see \Kshabazz\Sigma\Handlers\Block::buildBlocks()
	 * @access private
	 */
	private $blockHandler;

	/**
	 * Last character of a variable placeholder ( {VARIABLE_}_ )
	 * @var      string
	 * @access   public
	 * @see      $openingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	var $closingDelimiter = '}';

	/**
	 * Function name RegExp
	 * @var    string
	 */
	var $functionNameRegExp = '[_a-zA-Z][A-Za-z_0-9]*';

	/**
	 * Function name prefix used when searching for function calls in the template
	 * @var    string
	 */
	var $functionPrefix = 'func_';

	/**
	 * RegExp used to grep function calls in the template (set by the constructor)
	 * @var    string
	 * @see    _buildFunctionlist(), HTML_Template_Sigma()
	 */
	var $functionRegExp = '';

	/**
	 * First character of a variable placeholder ( _{_VARIABLE} ).
	 * @var      string
	 * @access   public
	 * @see      $closingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	var $openingDelimiter = '{';

	/**
	 * RegExp used to strip unused variable placeholders
	 * @see      $variablesRegExp, HTML_Template_Sigma()
	 */
	var $removeVariablesRegExp = '';

	/**
	 * RegExp used to find variable placeholder, filled by the constructor
	 * @var      string    Looks somewhat like @(delimiter varname delimiter)@
	 * @see      HTML_Template_Sigma()
	 */
	var $variablesRegExp = '';
	/**
	 * RegExp matching a variable placeholder in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var      string
	 * @access   public
	 * @see      $blocknameRegExp, $openingDelimiter, $closingDelimiter
	 */
	var $variableNameRegExp = '[0-9A-Za-z._-]+';

	/**
	 * Constructor: builds some complex regular expressions and optionally
	 * sets the root directories.
	 *
	 * Make sure that you call this constructor if you derive your template
	 * class from this one.
	 *
	 * @param string $pTemplate Template file to load.
	 * @param string $pCacheDir Directory to cache the "prepared" template.
	 *
	 * @see   setRoot(), setCacheRoot()
	 */
	public function __construct( $pTemplate = '', $pCacheDir = '' )
	{
		$this->variablesRegExp = \sprintf('@%s(%s)(:(%s))?%s@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp,
			$this->functionNameRegExp,
			$this->closingDelimiter
		);

		$this->removeVariablesRegExp = \sprintf('@%s\s*(%s)\s*'. $this->closingDelimiter . '@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp
		);

		$this->blockRegExp = \sprintf('@<!--\s+BEGIN\s+(%s)\s+-->(.*)<!--\s+END\s+\1\s+-->@sm', $this->blocknameRegExp);

		$this->functionRegExp = \sprintf('@%s(%s)\s*\(@sm', $this->functionPrefix, $this->functionNameRegExp);

		$this->setTemplate( $pTemplate );
		$this->setCacheDir( $pCacheDir );
//
//		$this->setCallbackFunction('h', [&$this, 'htmlspecialchars']);
//		$this->setCallbackFunction('e', [&$this, 'htmlentities']);
//		$this->setCallbackFunction('u', 'urlencode');
//		$this->setCallbackFunction('r', 'rawurlencode');
//		$this->setCallbackFunction('j', [&$this, 'jsEscape']);
	}

	/**
	 * Sets the directory to cache "prepared" templates in, the directory should be writable for PHP.
	 *
	 * The "prepared" template contains an internal representation of template
	 * structure: essentially a serialized array of $_blocks, $_blockVariables,
	 * $_children and $_functions, may also contain $_triggers. This allows
	 * to bypass expensive calls to _buildBlockVariables() and especially
	 * _buildBlocks() when reading the "prepared" template instead of
	 * the "source" one.
	 *
	 * The files in this cache do not have any TTL and are regenerated when the
	 * source templates change.
	 *
	 * NOTE: Caching will be turned off when directory is set to NULL.
	 *
	 * @param string $pDirectory Location of cache files.
	 * @see Parser(), _getCached(), _writeCache()
	 * @return \Kshabazz\Sigma\Parser
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function setCacheDir( $pDirectory )
	{
		// Report when invalid values are passed as an argument.
		if ( !\is_string($pDirectory) && !\is_null($pDirectory) )
		{
			throw new SigmaException(
				-17,
				NULL,
				sprintf( 'Argument passed to %s::%s() was invalid', __CLASS__, __FUNCTION__ )
			);
		}

		if ( empty($pDirectory) )
		{
			$pDirectory = NULL;
		}
		else if ( \is_dir($pDirectory) )
		{
			// Ensure the directory has the trailing slash, helps shorten code.
			if ( DIRECTORY_SEPARATOR != \substr($pDirectory, -1) )
			{
				$pDirectory .= DIRECTORY_SEPARATOR;
			}
		}
		else
		{ // When the directory does not exist and it is not empty, then throw an error.
			throw new SigmaException( SigmaException::BAD_CACHE_DIR, [$pDirectory] );
		}

		$this->_cacheRoot = $pDirectory;

		return $this;
	}

	/**
	 * Sets the template file to process.
	 *
	 * @param string $pTemplate Location to look for templates.
	 * @see \Kshabazz\Sigma\Parser()
	 * @return \Kshabazz\Sigma\Parser $this
	 * @throws \Kshabazz\Sigma\SigmaException When the directory does not exists.
	 */
	public function setTemplate( $pTemplate )
	{
		if ( \file_exists($pTemplate) )
		{
			throw new SigmaException(SigmaException::BAD_TEMPLATE, [$pTemplate] );
		}

		// Add a trailing slash, when missing.
		if ( !empty($pTemplate) && DIRECTORY_SEPARATOR != \substr($pTemplate, -1) )
		{
			$pTemplate .= DIRECTORY_SEPARATOR;
		}

		$this->fileRoot = $pTemplate;

		return $this;
	}
}
?>