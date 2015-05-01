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
	CALLBACK_SYNTAX_ERROR = -14;
// Phase this out, we should be able to detect the features we need.
define('Sigma\\OS_WINDOWS', \strtoupper(\substr(PHP_OS, 0, 3)) );

/**#@+
 * Error codes
 * @see HTML_Template_Sigma::errorMessage()
 */
const
	BAD_ROOT_ERROR = -15,
	BAD_CACHE_ROOT_ERROR = -16;

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
	private $blocks;

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
	 * @param string $root      root directory for templates
	 * @param string $cacheRoot directory to cache "prepared" templates in
	 *
	 * @see   setRoot(), setCacheRoot()
	 */
	public function __construct( $root = '', $cacheRoot = '' )
	{
		$this->variablesRegExp = '@'
			. $this->openingDelimiter . '('
			. $this->variableNameRegExp . ')(:('
			. $this->functionNameRegExp . '))?'
			. $this->closingDelimiter . '@sm';

		$this->removeVariablesRegExp = '@'
			. $this->openingDelimiter . '\s*('
			. $this->variableNameRegExp . ')\s*'
			. $this->closingDelimiter . '@sm';

		$this->blockRegExp = '@<!--\s+BEGIN\s+('
			. $this->blocknameRegExp
			. ')\s+-->(.*)<!--\s+END\s+\1\s+-->@sm';

		$this->functionRegExp = '@'
			. $this->functionPrefix . '('
			. $this->functionNameRegExp . ')\s*\(@sm';

		$this->setRoot( $root );
		$this->setCacheRoot( $cacheRoot );

		$this->setCallbackFunction('h', [&$this, 'htmlspecialchars']);
		$this->setCallbackFunction('e', [&$this, 'htmlentities']);
		$this->setCallbackFunction('u', 'urlencode');
		$this->setCallbackFunction('r', 'rawurlencode');
		$this->setCallbackFunction('j', [&$this, 'jsEscape']);
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
	 * @param string $pRoot directory name
	 * @see Parser(), _getCached(), _writeCache()
	 * @return \Kshabazz\Sigma\Parser
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function setCacheRoot( $pRoot )
	{
		// No caching will be use when directory is not set.
		if ( empty($pRoot) ) {
			$pRoot = null;
		}
		// Ensure the directory has the trailing slash, helps shorten code.
		else if ( \is_dir($pRoot) ) {
			if ( DIRECTORY_SEPARATOR != substr($pRoot, -1) ) {
				$pRoot .= DIRECTORY_SEPARATOR;
			}
			// Throw an error when the directory does not exist.
		} else {
			throw new SigmaException( BAD_CACHE_ROOT_ERROR, [$pRoot] );
		}

		$this->_cacheRoot = $pRoot;

		return $this;
	}

	/**
	 * Sets a callback function.
	 *
	 * Sigma templates can contain simple function calls. This means that the
	 * author of the template can add a special placeholder to it:
	 * <pre>
	 * func_h1("embedded in h1")
	 * </pre>
	 * Sigma will parse the template for these placeholders and will allow
	 * you to define a callback function for them. Callback will be called
	 * automatically when the block containing such function call is parse()'d.
	 *
	 * Please note that arguments to these template functions can contain
	 * variable placeholders: func_translate('Hello, {username}'), but not
	 * blocks or other function calls.
	 *
	 * This should NOT be used to add logic (except some presentation one) to
	 * the template. If you use a lot of such callbacks and implement business
	 * logic through them, then you're reinventing the wheel. Consider using
	 * XML/XSLT, native PHP or some other template engine.
	 *
	 * <code>
	 * function h_one($arg) {
	 *    return '<h1>' . $arg . '</h1>';
	 * }
	 * ...
	 * $tpl = new HTML_Template_Sigma( ... );
	 * ...
	 * $tpl->setCallbackFunction('h1', 'h_one');
	 * </code>
	 *
	 * template:
	 * <pre>
	 * func_h1('H1 Headline');
	 * </pre>
	 *
	 * @param string   $tplFunction Function name in the template
	 * @param callable $callback A callback: anything that can be passed to call_user_func_array()
	 * @param bool     $preserveArgs If true, then no variable substitution in arguments
	 *                               will take place before function call
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 */
	public function setCallbackFunction($tplFunction, callable $callback, $preserveArgs = false)
	{
		// TODO: Remove, as no longer needed since the type-hint "callable" was added.
		if ( !is_callable($callback) )
		{
			return new \Exception($this->errorMessage(SIGMA_INVALID_CALLBACK), SIGMA_INVALID_CALLBACK);
		}

		$this->_callback[$tplFunction] = [
			'data'         => $callback,
			'preserveArgs' => $preserveArgs
		];
		return SIGMA_OK;
	}

	/**
	 * Sets the file root for templates. The file root gets prefixed to all
	 * filenames passed to the object.
	 *
	 * @param string $pRoot directory name
	 * @see Parser()
	 * @return \Kshabazz\Sigma\Parser
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function setRoot( $pRoot )
	{
		$root = (string) $pRoot;
		// Add a trailing slash, when missing.
		if ( !empty($root) && DIRECTORY_SEPARATOR != \substr($root, -1) )
		{
			$root .= DIRECTORY_SEPARATOR;
		}

		$this->fileRoot = $root;

		return $this;
	}

	/**
	 * Wrapper around htmlspecialchars() needed to use the charset option
	 *
	 * @param string $value String with special characters
	 *
	 * @access private
	 * @return string
	 */
	private function htmlspecialchars($value)
	{
		return htmlspecialchars($value, ENT_COMPAT, $this->_options['charset']);
	}


	/**
	 * Wrapper around htmlentities() needed to use the charset option
	 *
	 * @param string $value String with special characters
	 *
	 * @access private
	 * @return string
	 */
	private function htmlentities($value)
	{
		return htmlentities($value, ENT_COMPAT, $this->_options['charset']);
	}

	/**
	 * Quotes the string so that it can be used in Javascript string constants
	 *
	 * @param string $value String to be used in JS
	 *
	 * @access private
	 * @return string
	 */
	private function jsEscape($value)
	{
		// Characters to translate.
		$map = [
			"\r" => '\r',
			"'"  => "\\x27",
			"\n" => '\n',
			'"'  => '\\x22',
			"\t" => '\t',
			'\\' => '\\\\'
		];

		return \strtr( $value, $map );
	}
}
?>