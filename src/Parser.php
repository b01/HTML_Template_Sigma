<?php namespace Kshabazz\Sigma;
/**
 * Implementation of Integrated Templates API with template 'compilation' added.
 *
 * @copyright 2014
 * @license   http://www.php.net/license/3_01.txt PHP License 3.01
 * @link      http://pear.php.net/package/HTML_Template_Sigma
 */

/**
 * Class Parser
 *
 * @package Kshabazz\Sigma
 */
class Parser
{

	/**
	 * First character of a variable placeholder ( _{_VARIABLE} ).
	 * @var      string
	 * @see      $closingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	public $openingDelimiter = '{';

	/**
	 * Last character of a variable placeholder ( {VARIABLE_}_ )
	 * @var      string
	 * @see      $openingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	public $closingDelimiter = '}';

	/**
	 * RegExp for matching the block names in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var      string
	 * @see      $variablenameRegExp, $openingDelimiter, $closingDelimiter
	 */
	public $blocknameRegExp = '[0-9A-Za-z_-]+';

	/**
	 * RegExp matching a variable placeholder in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var      string
	 * @see      $blocknameRegExp, $openingDelimiter, $closingDelimiter
	 */
	public $variablenameRegExp = '[0-9A-Za-z._-]+';

	/** @var boolean Controls the handling of unknown variables, default is remove */
	public $removeUnknownVariables = true;

	/** @var boolean Controls the handling of empty blocks, default is remove */
	public $removeEmptyBlocks = true;

	/**
	 * RegExp used to find variable placeholder, filled by the constructor
	 * @var      string    Looks somewhat like @(delimiter varname delimiter)@
	 * @see      HTML_Template_Sigma()
	 */
	var $variablesRegExp = '';

	/**
	 * RegExp used to strip unused variable placeholders
	 * @see      $variablesRegExp, HTML_Template_Sigma()
	 */
	var $removeVariablesRegExp = '';

	/**
	 * RegExp used to find blocks and their content, filled by the constructor
	 * @var      string
	 * @see      HTML_Template_Sigma()
	 */
	var $blockRegExp = '';

	/**
	 * Name of the current block
	 * @var      string
	 */
	var $currentBlock = '__global__';

	/**
	 * Function name prefix used when searching for function calls in the template
	 * @var    string
	 */
	var $functionPrefix = 'func_';

	/**
	 * Function name RegExp
	 * @var    string
	 */
	var $functionnameRegExp = '[_a-zA-Z][A-Za-z_0-9]*';

	/**
	 * RegExp used to grep function calls in the template (set by the constructor)
	 * @var    string
	 * @see    _buildFunctionlist(), HTML_Template_Sigma()
	 */
	var $functionRegExp = '';

	/**
	 * RegExp used to find file inclusion calls in the template
	 * @var  string
	 */
	var $includeRegExp = '#<!--\s+INCLUDE\s+(\S+)\s+-->#im';

	/**
	 * RegExp used to find (and remove) comments in the template
	 * @var  string
	 */
	var $commentRegExp = '#<!--\s+COMMENT\s+-->.*?<!--\s+/COMMENT\s+-->#sm';

	/**
	 * Global variables for substitution
	 *
	 * These are substituted into all blocks, are not cleared on
	 * block parsing and do not trigger "non-empty" logic. I.e. if
	 * only global variables are substituted into the block, it is
	 * still considered "empty".
	 *
	 * @var      array
	 * @see      setVariable(), setGlobalVariable()
	 */
	private $_globalVariables = array();

	/**
	 * Root directory for "source" templates
	 * @var    string
	 * @see    HTML_Template_Sigma(), setRoot()
	 */
	private $fileRoot = '';

	/**
	 * Directory to store the "prepared" templates in
	 * @var      string
	 * @see      HTML_Template_Sigma(), setCacheRoot()
	 * @access   private
	 */
	private $_cacheRoot = null;

	/**
	 * Flag indicating that the global block was parsed
	 * @var    boolean
	 */
	private $flagGlobalParsed = false;

	/** @var array Options to control some finer aspects of Sigma's work. */
	private $_options = array(
		'preserve_data' => false,
		'trim_on_save'  => true,
		'charset'       => 'iso-8859-1'
	);

	/**
	 * Template blocks and their content
	 * @var      array
	 * @see      _buildBlocks()
	 * @access   private
	 */
	private $_blocks = array();

	/**
	 * Content of parsed blocks
	 * @var      array
	 * @see      get(), parse()
	 */
	private $_parsedBlocks = array();

	/**
	 * Variable names that appear in the block
	 * @var      array
	 * @see      _buildBlockVariables()
	 */
	private $_blockVariables = array();

	/**
	 * Inner blocks inside the block
	 * @var      array
	 * @see      _buildBlocks()
	 */
	private $_children = array();

	/**
	 * List of blocks to preserve even if they are "empty"
	 * @var      array
	 * @see      touchBlock(), $removeEmptyBlocks
	 */
	private $_touchedBlocks = array();

	/**
	 * List of blocks which should not be shown even if not "empty"
	 * @var      array
	 * @see      hideBlock(), $removeEmptyBlocks
	 */
	private $_hiddenBlocks = array();

	/**
	 * Variables for substitution.
	 *
	 * Variables are kept in this array before the replacements are done.
	 * This allows automatic removal of empty blocks.
	 *
	 * @var      array
	 * @see      setVariable()
	 */
	private $_variables = array();

	/** @var array List of functions found in the template. */
	private $_functions = array();

	/** @var array List of callback functions specified by the user */
	private $_callback = array();

	/** @var array Files queued for inclusion */
	private $_triggers = array();

	/** @var string Name of the block to use in _makeTrigger() (see bug #20068) */
	private $_triggerBlock = '__global__';

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
	function __construct($root , $cacheRoot)
	{
		$this->variablesRegExp       = '@' . $this->openingDelimiter . '(' . $this->variablenameRegExp . ')' .
			'(:(' . $this->functionnameRegExp . '))?' . $this->closingDelimiter . '@sm';
		$this->removeVariablesRegExp = '@' . $this->openingDelimiter . '\s*(' . $this->variablenameRegExp . ')\s*'
			. $this->closingDelimiter . '@sm';
		$this->blockRegExp           = '@<!--\s+BEGIN\s+(' . $this->blocknameRegExp
			. ')\s+-->(.*)<!--\s+END\s+\1\s+-->@sm';
		$this->functionRegExp        = '@' . $this->functionPrefix . '(' . $this->functionnameRegExp . ')\s*\(@sm';
		$this->setRoot($root);
		$this->setCacheRoot($cacheRoot);

		$this->setCallbackFunction('h', array(&$this, '_htmlspecialchars'));
		$this->setCallbackFunction('e', array(&$this, '_htmlentities'));
		$this->setCallbackFunction('u', 'urlencode');
		$this->setCallbackFunction('r', 'rawurlencode');
		$this->setCallbackFunction('j', array(&$this, '_jsEscape'));
	}

	/**
	 * Returns a textual error message for an error code
	 *
	 * @param integer $code error code or another error object for code reuse
	 * @param string $data additional data to insert into message
	 * @return string error message
	 */
	public function errorMessage($code, $data = null)
	{
		static $errorMessages;
		if (!isset($errorMessages)) {
			$errorMessages = array(
				SIGMA_ERROR                 => 'unknown error',
				SIGMA_OK                    => '',
				SIGMA_TPL_NOT_FOUND         => 'Cannot read the template file \'%s\'',
				SIGMA_BLOCK_NOT_FOUND       => 'Cannot find block \'%s\'',
				SIGMA_BLOCK_DUPLICATE       => 'The name of a block must be unique within a template. '
					. 'Block \'%s\' found twice.',
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
		}

		if ( !\array_key_exists($code, $errorMessages) )
		{
			return $errorMessages[SIGMA_ERROR];
		}
		else
		{
			return ( null === $data )? $errorMessages[ $code ]: \sprintf( $errorMessages[$code], $data );
		}
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
		if (is_dir($pRoot)) {
			$this->_cacheRoot = $pRoot;
			return $this;
		}

		throw new SigmaException(
			$this->errorMessage(SIGMA_BAD_CACHE_ROOT_ERROR),
			SIGMA_BAD_CACHE_ROOT_ERROR
		);
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
	 * @param string   $tplFunction  Function name in the template
	 * @param callable $callback     A callback: anything that can be passed to call_user_func_array()
	 * @param bool     $preserveArgs If true, then no variable substitution in arguments
	 *                               will take place before function call
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws SigmaException
	 */
	public function setCallbackFunction($tplFunction, $callback, $preserveArgs = false)
	{
		if (!is_callable($callback)) {
			return new SigmaException($this->errorMessage(SIGMA_INVALID_CALLBACK), SIGMA_INVALID_CALLBACK);
		}
		$this->_callback[$tplFunction] = array(
			'data'         => $callback,
			'preserveArgs' => $preserveArgs
		);
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
		if ( \is_dir($pRoot) )
		{
			$this->fileRoot = $pRoot;
			return $this;
		}

		throw new SigmaException(
			$this->errorMessage(SIGMA_BAD_ROOT_ERROR),
			SIGMA_BAD_ROOT_ERROR
		);
	}
}
?>