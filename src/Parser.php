<?php namespace Kshabazz\Sigma;
/**
 * This is the core Sigma class which processes template files by way of parsing/setting blocks, placeholders, and
 * handling of caching. This class is what a developer will generally instantiate in their code.
 *
 * Originally this was Sigma/Sigma, but has been rewritten to break out core pieces, in an effort to make it more
 * readable. In the hopes that it will be simpler to maintain and improve as time goes on.
 */

use \Kshabazz\Sigma\Handlers\Block,
	\Kshabazz\Sigma\Handlers\Variables;

/**
 * Class Parser
 *
 * @package Kshabazz\Sigma
 */
class Parser
{
	const
		/**
		 * @const Key for setting preserve data option.
		 */
		OPTION_PRESERVE_DATA = 'preserve_data',
		/**
		 * @const Trim white-space on save option.
		 */
		OPTION_TRIM_ON_SAVE = 'trim_on_save',
		/**
		 * @const Key for setting the character set option.
		 */
		OPTION_CHARSET = 'charset',
		/**
		 * @const Key for setting the cache directory option.
		 */
		OPTION_CACHE_DIR = 'cache_dir';

	/**
	 * RegExp for matching the block names in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 *
	 * @var string
	 * @see $variablenameRegExp, $openingDelimiter, $closingDelimiter
	 */
	private $blockNameRegExp = '[0-9A-Za-z_-]+';

	/**
	 * RegExp used to find blocks and their content, filled by the constructor
	 *
	 * @var string
	 * @see \Kshabazz\Sigma\Parser()
	 */
	private $blockRegExp = '';

	/**
	 * Directory to store the "prepared" templates.
	 *
	 * @var string
	 * @see \Kshabazz\Sigma\Parser::setCacheRoot()
	 */
	private $cacheDir;

	/**
	 * Last character of a variable placeholder ( {VARIABLE_}_ )
	 *
	 * @var string
	 * @see $openingDelimiter, $blockNameRegExp, $variableNameRegExp
	 */
	private $closingDelimiter = '}';

	/**
	 * Function name RegExp
	 *
	 * @var string
	 */
	private $functionNameRegExp = '[_a-zA-Z][A-Za-z_0-9]*';

	/**
	 * Function name prefix used when searching for function calls in the template
	 * @var string
	 */
	private $functionPrefix = 'func_';

	/**
	 * RegExp used to grep function calls in the template (set by the constructor)
	 * @var    string
	 * @see    _buildFunctionlist(), HTML_Template_Sigma()
	 */
	private $functionRegExp = '';

	/**
	 * First character of a variable placeholder ( _{_VARIABLE} ).
	 * @var      string
	 * @access   public
	 * @see      $closingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	private $openingDelimiter = '{';

	/**
	 * Options to control some finer aspects of Sigma's work.
	 *
	 * @var      array
	 * @access   private
	 */
	private $options;

	/**
	 * RegExp used to strip unused variable placeholders
	 * @see      $variablesRegExp, HTML_Template_Sigma()
	 */
	private $removeVariablesRegExp = '';

	/**
	 * @var string Template file to parse.
	 */
	private $templateFile;

	/**
	 * Variables for substitution.
	 *
	 * Variables are kept in this array before the replacements are done.
	 * This allows automatic removal of empty blocks.
	 *
	 * @var      array
	 * @see      setVariable()
	 * @access   private
	 */
	private $variableHandler = [];

	/**
	 * RegExp used to find variable placeholder, filled by the constructor
	 * @var      string    Looks somewhat like @(delimiter varname delimiter)@
	 * @see      HTML_Template_Sigma()
	 */
	private $variablesRegExp = '';

	/**
	 * RegExp matching a variable placeholder in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var      string
	 * @access   public
	 * @see      $blocknameRegExp, $openingDelimiter, $closingDelimiter
	 */
	private $variableNameRegExp = '[0-9A-Za-z._-]+';

	/**
	 * Constructor: builds some complex regular expressions and optionally
	 * sets the root directories.
	 *
	 * Make sure that you call this constructor if you derive your template
	 * class from this one.
	 *
	 * @param array $pOptions
	 *              cache_dir: Directory to cache the "prepared" template.
	 *
	 * @see \Sk\Kshabazz\Parser::setCacheDir()
	 */
	public function __construct( array $pOptions = NULL )
	{
		// BEGIN defaults
		$this->variablesRegExp = \sprintf( '@%s(%s)(:(%s))?%s@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp,
			$this->functionNameRegExp,
			$this->closingDelimiter
		);

		$this->removeVariablesRegExp = \sprintf( '@%s\s*(%s)\s*'. $this->closingDelimiter . '@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp
		);

		$this->blockRegExp = \sprintf( '@<!--\s+BEGIN\s+(%s)\s+-->(.*)<!--\s+END\s+\1\s+-->@sm',
			$this->blockNameRegExp
		);

		$this->functionRegExp = \sprintf( '@%s(%s)\s*\(@sm', $this->functionPrefix, $this->functionNameRegExp );

		$this->options = [
			self::OPTION_PRESERVE_DATA => FALSE,
			self::OPTION_TRIM_ON_SAVE => TRUE,
			self::OPTION_CHARSET => 'iso-8859-1',
			self::OPTION_CACHE_DIR => NULL
		];
		// END Defaults

		// Override default options.
		if ( \is_array($pOptions) )
		{
			foreach ( $pOptions as $key => $value )
			{
				$this->setOption( $key, $value );
			}
		}

		$this->setCacheDir( $this->options[self::OPTION_CACHE_DIR] );

		// Set template processing utilities.
		$this->setCallbackFunction( 'h', [$this, 'htmlSpecialChars'] );
		$this->setCallbackFunction( 'e', [$this, 'htmlEntities'] );
		$this->setCallbackFunction( 'u', '\\urlencode' );
		$this->setCallbackFunction( 'r', '\\rawurlencode' );
		$this->setCallbackFunction( 'j', [$this, 'jsEscape'] );

		$this->variableHandler = new Variables();
	}

	/**
	 * Loads a template file.
	 *
	 * If caching is on, then it checks whether a "prepared" template exists.
	 * If it does, it gets loaded instead of the original, if it does not, then
	 * the original gets loaded and prepared and then the prepared version is saved.
	 * addBlockfile() and replaceBlockfile() implement quite the same logic.
	 *
	 * @param string $pTemplateFile Path to a template file.
	 * @param boolean $removeUnknownVariables remove unknown/unused variables?
	 * @param boolean $removeEmptyBlocks Remove blocks which do no contain any placeholders. A good use case for this
	 *                is when the template contains generic content blocks, and you want to use logic to determine
	 *                which ones to display using touchBlock()
	 *
	 * @return boolean TRUE on success.
	 * @throws \Kshabazz\Sigma\SigmaException
	 * @see    setTemplate(), $removeUnknownVariables, $removeEmptyBlocks
	 */
	public function loadTemplateFile($pTemplateFile, $removeUnknownVariables = true, $removeEmptyBlocks = true)
	{
//		if ($this->isCached($pTemplateFile)) {
//			$this->resetTemplate($removeUnknownVariables, $removeEmptyBlocks);
//			return $this->getCached($filename);
//		}

		if ( !\file_exists($pTemplateFile) )
		{
			throw new SigmaException( SigmaException::BAD_TEMPLATE, [$pTemplateFile] );
		}

		// Set and load the template.
		$this->templateFile = $pTemplateFile;
		$template = \file_get_contents( $this->templateFile );

		// When unable to read the template.
		if ( $template === FALSE ) {
			throw new SigmaException( SigmaException::TPL_NOT_FOUND, [$pTemplateFile] );
		}

//		$this->_triggers = [];
//		$this->_triggerBlock = '__global__';
//		if (SIGMA_OK !== ($res = $this->setTemplate($template, $removeUnknownVariables, $removeEmptyBlocks, $pTemplateFile))) {
//			return $res;
//		}

		return TRUE;
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
	 * NOTE: Caching can be turned off by setting the cache directory to NULL.
	 *
	 * @param string $pDirectory Location of cache files.
	 * @see \Kshabazz\Sigma\Parser(), \Kshabazz\Sigma\Parser::getCached(), \Kshabazz\Sigma\Parser::writeCache()
	 * @return \Kshabazz\Sigma\Parser
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function setCacheDir( $pDirectory )
	{
		// When invalid value is passed as an argument.
		if ( !\is_string($pDirectory) && !\is_null($pDirectory) )
		{
			throw new SigmaException( SigmaException::BAD_CACHE_DIR, [$pDirectory] );
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

		$this->cacheDir = $pDirectory;

		return $this;
	}

	/**
	 * Sets the option for the template class
	 *
	 * Currently available options:
	 * - preserve_data: If false (default), then substitute variables and
	 *   remove empty placeholders in data passed through setVariable (see also
	 *   PHP bugs #20199, #21951)
	 * - trim_on_save: Whether to trim extra whitespace from template on cache
	 *   save (defaults to true). Generally safe to leave this on, unless you
	 *   have <<pre>><</pre>> in templates or want to preserve HTML indentantion
	 * - charset: is used by builtin template callback 'h'/'e'. Defaults to 'iso-8859-1'
	 *
	 * @param string $pOption Option name
	 * @param mixed $pValue Option value
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	function setOption( $pOption, $pValue )
	{
		if ( \array_key_exists($pOption, $this->options) ) {
			$this->options[ $pOption ] = $pValue;
			return TRUE;
		}

		throw new SigmaException( SigmaException::UNKNOWN_OPTION, [$pOption] );
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
	 * @param string $tplFunction Function name in the template
	 * @param callable $callback A callback: anything that can be passed to call_user_func_array()
	 * @param bool $preserveArgs If true, then no variable substitution in arguments
	 *                           will take place before function call
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws \Exception
	 */
	public function setCallbackFunction($tplFunction, callable $callback, $preserveArgs = false)
	{
		$this->_callback[ $tplFunction ] = [
			'data'         => $callback,
			'preserveArgs' => $preserveArgs
		];

		return SIGMA_OK;
	}
	/**
	 * Sets a variable value.
	 *
	 * The function can be used either like setVariable("varname", "value")
	 * or with one array $variables["varname"] = "value" given setVariable($variables)
	 *
	 * If $value is an array ('key' => 'value', ...) then values from that array
	 * will be assigned to template placeholders of the form {variable.key}, ...
	 *
	 * @param string|array $variable variable name or array ('varname' => 'value')
	 * @param string|array $value    variable value if $variable is not an array
	 *
	 * @access public
	 * @return void
	 * TODO: split this array part off as setVariables(array), keys are placeholders, values are the variables.
	 */
	function setVariable( $variable, $value = '' )
	{
		if ( \is_array($variable)) {
			$this->variableHandler->setVariable( $variable );
		} else {
			$this->variableHandler->setVariable( $variable, $value );
		}
	}

	/**
	 * Wrapper around htmlSpecialChars() needed to use the charset option
	 *
	 * @param string $pValue String with special characters
	 *
	 * @access private
	 * @return string
	 */
	private function htmlSpecialChars( $pValue )
	{
		return htmlspecialchars( $pValue, ENT_COMPAT, $this->options['charset'] );
	}

	/**
	 * Wrapper around htmlentities() needed to use the charset option
	 *
	 * @param string $pValue String with special characters.
	 *
	 * @return string
	 */
	private function htmlEntities( $pValue )
	{
		return \htmlentities( $pValue, ENT_COMPAT, $this->options['charset'] );
	}

	/**
	 * Quotes the string so that it can be used in Javascript string constants
	 *
	 * @param string $pValue String to be used in JS
	 *
	 * @access private
	 * @return string
	 */
	private function jsEscape( $pValue )
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

		return \strtr( $pValue, $map );
	}
}
?>