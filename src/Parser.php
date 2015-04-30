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
	 * Content of parsed blocks
	 * @var      array
	 * @see      get(), parse()
	 */
	private $_parsedBlocks = array();


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
	function __construct($root , $cacheRoot = null)
	{
		$this->variablesRegExp       = '@' . $this->openingDelimiter . '(' . $this->variablenameRegExp . ')' .
			'(:(' . $this->functionnameRegExp . '))?' . $this->closingDelimiter . '@sm';
		$this->removeVariablesRegExp = '@' . $this->openingDelimiter . '\s*(' . $this->variablenameRegExp . ')\s*'
			. $this->closingDelimiter . '@sm';
		$this->functionRegExp        = '@' . $this->functionPrefix . '(' . $this->functionnameRegExp . ')\s*\(@sm';

		$this->setCallbackFunction('h', array(&$this, '_htmlspecialchars'));
		$this->setCallbackFunction('e', array(&$this, '_htmlentities'));
		$this->setCallbackFunction('u', 'urlencode');
		$this->setCallbackFunction('r', 'rawurlencode');
		$this->setCallbackFunction('j', array(&$this, '_jsEscape'));
	}

	/**
	 * Parses the current block
	 *
	 * @see    parse(), setCurrentBlock()
	 * @return bool whether the block was "empty"
	 */
	public function parseCurrentBlock()
	{
		return $this->parse($this->currentBlock);
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
	 * @param string $option option name
	 * @param mixed  $value  option value
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 */
	function setOption($option, $value)
	{
		if (isset($this->_options[$option])) {
			$this->_options[$option] = $value;
			return SIGMA_OK;
		}
		return new \Exception($this->errorMessage(SIGMA_UNKNOWN_OPTION, $option), SIGMA_UNKNOWN_OPTION);
	}

	/**
	 * Prints a block with all replacements done.
	 *
	 * @param string $block block name
	 *
	 * @access  public
	 * @return  void
	 * @see     get()
	 */
	function show($block = '__global__')
	{
		print $this->get($block);
	}


	/**
	 * Returns a block with all replacements done.
	 *
	 * @param string $block block name
	 * @param bool   $clear whether to clear parsed block contents
	 *
	 * @return string block with all replacements done
	 * @throws SigmaException
	 * @access public
	 * @see    show()
	 */
	function get($block = '__global__', $clear = false)
	{
		// When the block is not in the list, throw an error.
		if (!isset($this->_blocks[$block])) {
			throw new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}

		// I think this parses thow whole template and populates $this->_parsedBlocks.
		if ('__global__' == $block && !$this->flagGlobalParsed) {
			$this->parse('__global__');
		}

		// return the parsed block, removing the unknown placeholders if needed
		if (!isset($this->_parsedBlocks[$block])) {
			return '';
		}

		$ret = $this->_parsedBlocks[$block];
		if ($clear) {
			unset($this->_parsedBlocks[$block]);
		}
		if ($this->removeUnknownVariables) {
			$ret = preg_replace($this->removeVariablesRegExp, '', $ret);
		}
		if ($this->_options['preserve_data']) {
			$ret = str_replace(
				$this->openingDelimiter . '%preserved%' . $this->closingDelimiter, $this->openingDelimiter, $ret
			);
		}
		return $ret;
	}


	/**
	 * Parses the given block.
	 *
	 * @param string $block         block name
	 * @param bool   $flagRecursion true if the function is called recursively (do not set this to true yourself!)
	 * @param bool   $fakeParse     true if parsing a "hidden" block (do not set this to true yourself!)
	 *
	 * @return bool whether the block was "empty"
	 * @access public
	 * @see    parseCurrentBlock()
	 * @throws PEAR_Error
	 */
	function parse($block = '__global__', $flagRecursion = false, $fakeParse = false)
	{
		static $vars;

		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		if ('__global__' == $block) {
			$this->flagGlobalParsed = true;
		}
		if (!isset($this->_parsedBlocks[$block])) {
			$this->_parsedBlocks[$block] = '';
		}
		$outer = $this->_blocks[$block];

		if (!$flagRecursion) {
			$vars = array();
		}
		// block is not empty if its local var is substituted
		$empty = true;
		foreach ($this->_blockVariables[$block] as $allowedvar => $v) {
			if (isset($this->_variables[$allowedvar])) {
				$vars[$this->openingDelimiter . $allowedvar . $this->closingDelimiter] = $this->_variables[$allowedvar];
				$empty = false;
				// vital for checking "empty/nonempty" status
				unset($this->_variables[$allowedvar]);
			}
		}

		// processing of the inner blocks
		if (isset($this->_children[$block])) {
			foreach ($this->_children[$block] as $innerblock => $v) {
				$placeholder = $this->openingDelimiter.'__'.$innerblock.'__'.$this->closingDelimiter;

				if (isset($this->_hiddenBlocks[$innerblock])) {
					// don't bother actually parsing this inner block; but we _have_
					// to go through its local vars to prevent problems on next iteration
					$this->parse($innerblock, true, true);
					unset($this->_hiddenBlocks[$innerblock]);
					$outer = str_replace($placeholder, '', $outer);

				} else {
					$this->parse($innerblock, true, $fakeParse);
					// block is not empty if its inner block is not empty
					if ('' != $this->_parsedBlocks[$innerblock]) {
						$empty = false;
					}

					$outer = str_replace($placeholder, $this->_parsedBlocks[$innerblock], $outer);
					$this->_parsedBlocks[$innerblock] = '';
				}
			}
		}

		// add "global" variables to the static array
		foreach ($this->_globalVariables as $allowedvar => $value) {
			if (isset($this->_blockVariables[$block][$allowedvar])) {
				$vars[$this->openingDelimiter . $allowedvar . $this->closingDelimiter] = $value;
			}
		}
		// if we are inside a hidden block, don't bother
		if (!$fakeParse) {
			if (0 != count($vars) && (!$flagRecursion || !empty($this->_functions[$block]))) {
				$varKeys     = array_keys($vars);
				$varValues   = $this->_options['preserve_data']
					? array_map(array(&$this, '_preserveOpeningDelimiter'), array_values($vars))
					: array_values($vars);
			}

			// check whether the block is considered "empty" and append parsed content if not
			if (!$empty || '__global__' == $block
				|| !$this->removeEmptyBlocks || isset($this->_touchedBlocks[$block])
			) {
				// perform callbacks
				if (!empty($this->_functions[$block])) {
					foreach ($this->_functions[$block] as $id => $data) {
						$placeholder = $this->openingDelimiter . '__function_' . $id . '__' . $this->closingDelimiter;
						// do not waste time calling function more than once
						if (!isset($vars[$placeholder])) {
							$args         = array();
							$preserveArgs = !empty($this->_callback[$data['name']]['preserveArgs']);
							foreach ($data['args'] as $arg) {
								$args[] = (empty($varKeys) || $preserveArgs)
									? $arg
									: str_replace($varKeys, $varValues, $arg);
							}
							if (isset($this->_callback[$data['name']]['data'])) {
								$res = call_user_func_array($this->_callback[$data['name']]['data'], $args);
							} else {
								$res = isset($args[0])? $args[0]: '';
							}
							$outer = str_replace($placeholder, $res, $outer);
							// save the result to variable cache, it can be requested somewhere else
							$vars[$placeholder] = $res;
						}
					}
				}
				// substitute variables only on non-recursive call, thus all
				// variables from all inner blocks get substituted
				if (!$flagRecursion && !empty($varKeys)) {
					$outer = str_replace($varKeys, $varValues, $outer);
				}

				$this->_parsedBlocks[$block] .= $outer;
				if (isset($this->_touchedBlocks[$block])) {
					unset($this->_touchedBlocks[$block]);
				}
			}
		}
		return $empty;
	}

	/**
	 * Sets the template.
	 *
	 * You can either load a template file from disk with LoadTemplatefile() or set the
	 * template manually using this function.
	 *
	 * @param string  $template               template content
	 * @param boolean $removeUnknownVariables remove unknown/unused variables?
	 * @param boolean $removeEmptyBlocks      remove empty blocks?
	 * @param boolean $cacheFilename          name of writeCache file
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    loadTemplatefile()
	 */
	function setTemplate($template, $removeUnknownVariables = true, $removeEmptyBlocks = true, $cacheFilename = null)
	{
		$template = preg_replace_callback($this->includeRegExp, array(&$this, '_makeTrigger'), $template);

		$this->_resetTemplate($removeUnknownVariables, $removeEmptyBlocks);
		$list = $this->_buildBlocks(
			'<!-- BEGIN __global__ -->' .
			preg_replace($this->commentRegExp, '', $template) .
			'<!-- END __global__ -->'
		);
		if (is_a($list, 'PEAR_Error')) {
			return $list;
		}
		if (SIGMA_OK !== ($res = $this->_buildBlockVariables())) {
			return $res;
		}
		return $this->_writeCache($cacheFilename, '__global__');
	}

	/**
	 * Quotes the string so that it can be used in Javascript string constants
	 *
	 * @param string $value String to be used in JS
	 *
	 * @access private
	 * @return string
	 */
	function _jsEscape($value)
	{
		return strtr(
			$value,
			array(
				"\r" => '\r',    "'"  => "\\x27", "\n" => '\n',
				'"'  => '\\x22', "\t" => '\t',    '\\' => '\\\\'
			)
		);
	}

	/**
	 * Wrapper around htmlentities() needed to use the charset option
	 *
	 * @param string $value String with special characters
	 *
	 * @access private
	 * @return string
	 */
	function _htmlentities($value)
	{
		return htmlentities($value, ENT_COMPAT, $this->_options['charset']);
	}

	/**
	 * Wrapper around htmlspecialchars() needed to use the charset option
	 *
	 * @param string $value String with special characters
	 *
	 * @access private
	 * @return string
	 */
	function _htmlspecialchars($value)
	{
		return htmlspecialchars($value, ENT_COMPAT, $this->_options['charset']);
	}

	/**
	 * Loads a template file.
	 *
	 * If caching is on, then it checks whether a "prepared" template exists.
	 * If it does, it gets loaded instead of the original, if it does not, then
	 * the original gets loaded and prepared and then the prepared version is saved.
	 * addBlockfile() and replaceBlockfile() implement quite the same logic.
	 *
	 * @param string  $filename               filename
	 * @param boolean $removeUnknownVariables remove unknown/unused variables?
	 * @param boolean $removeEmptyBlocks      remove empty blocks?
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    setTemplate(), $removeUnknownVariables, $removeEmptyBlocks
	 */
	public function loadTemplateFile($filename, $removeUnknownVariables = true, $removeEmptyBlocks = true)
	{
		if ($this->_isCached($filename)) {
			$this->_resetTemplate($removeUnknownVariables, $removeEmptyBlocks);
			return $this->_getCached($filename);
		}
		if (false === ($template = @file_get_contents($this->fileRoot . $filename))) {
			return new \Exception($this->errorMessage(SIGMA_TPL_NOT_FOUND, $filename), SIGMA_TPL_NOT_FOUND);
		}
		$this->_triggers     = array();
		$this->_triggerBlock = '__global__';
		if (SIGMA_OK !== ($res = $this->setTemplate($template, $removeUnknownVariables, $removeEmptyBlocks, $filename))) {
			return $res;
		}
	}


	/**
	 * Recursively builds a list of all variables within a block.
	 *
	 * Also calls _buildFunctionlist() for each block it visits
	 *
	 * @param string $block block name
	 *
	 * @access private
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    _buildFunctionlist()
	 */
	function _buildBlockVariables($block = '__global__')
	{
		$this->_blockVariables[$block] = array();
		$this->_functions[$block]      = array();
		preg_match_all($this->variablesRegExp, $this->_blocks[$block], $regs, PREG_SET_ORDER);
		foreach ($regs as $match) {
			$this->_blockVariables[$block][$match[1]] = true;
			if (!empty($match[3])) {
				$funcData = array(
					'name' => $match[3],
					'args' => array($this->openingDelimiter . $match[1] . $this->closingDelimiter)
				);
				$funcId   = substr(md5(serialize($funcData)), 0, 10);

				// update block info
				$this->_blocks[$block] = str_replace(
					$match[0],
					$this->openingDelimiter . '__function_' . $funcId . '__' . $this->closingDelimiter,
					$this->_blocks[$block]
				);
				$this->_blockVariables[$block]['__function_' . $funcId . '__'] = true;
				$this->_functions[$block][$funcId] = $funcData;
			}
		}
		if (SIGMA_OK != ($res = $this->_buildFunctionlist($block))) {
			return $res;
		}
		if (isset($this->_children[$block]) && is_array($this->_children[$block])) {
			foreach ($this->_children[$block] as $child => $v) {
				if (SIGMA_OK != ($res = $this->_buildBlockVariables($child))) {
					return $res;
				}
			}
		}
		return SIGMA_OK;
	}

	/**
	 * Builds a list of functions in a block.
	 *
	 * @param string $block Block name
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    _buildBlockVariables()
	 */
	private function _buildFunctionlist($block)
	{
		$template = $this->_blocks[$block];
		$this->_blocks[$block] = '';

		while (preg_match($this->functionRegExp, $template, $regs)) {
			$this->_blocks[$block] .= substr($template, 0, strpos($template, $regs[0]));
			$template = substr($template, strpos($template, $regs[0]) + strlen($regs[0]));

			$state    = 1;
			$arg      = '';
			$quote    = '';
			$funcData = array(
				'name' => $regs[1],
				'args' => array()
			);
			for ($i = 0, $len = strlen($template); $i < $len; $i++) {
				$char = $template[$i];
				switch ($state) {
					case 0:
					case -1:
						break 2;

					case 1:
						if (')' == $char) {
							$state = 0;
						} elseif (',' == $char) {
							$error = 'Unexpected \',\'';
							$state = -1;
						} elseif ('\'' == $char || '"' == $char) {
							$quote = $char;
							$state = 5;
						} elseif (!ctype_space($char)) {
							$arg  .= $char;
							$state = 3;
						}
						break;

					case 2:
						$arg = '';
						if (',' == $char || ')' == $char) {
							$error = 'Unexpected \'' . $char . '\'';
							$state = -1;
						} elseif ('\'' == $char || '"' == $char) {
							$quote = $char;
							$state = 5;
						} elseif (!ctype_space($char)) {
							$arg  .= $char;
							$state = 3;
						}
						break;

					case 3:
						if (')' == $char) {
							$funcData['args'][] = rtrim($arg);
							$state  = 0;
						} elseif (',' == $char) {
							$funcData['args'][] = rtrim($arg);
							$state = 2;
						} elseif ('\'' == $char || '"' == $char) {
							$quote = $char;
							$arg  .= $char;
							$state = 4;
						} else {
							$arg  .= $char;
						}
						break;

					case 4:
						$arg .= $char;
						if ($quote == $char) {
							$state = 3;
						}
						break;

					case 5:
						if ('\\' == $char) {
							$state = 6;
						} elseif ($quote == $char) {
							$state = 7;
						} else {
							$arg .= $char;
						}
						break;

					case 6:
						$arg  .= $char;
						$state = 5;
						break;

					case 7:
						if (')' == $char) {
							$funcData['args'][] = $arg;
							$state  = 0;
						} elseif (',' == $char) {
							$funcData['args'][] = $arg;
							$state  = 2;
						} elseif (!ctype_space($char)) {
							$error = 'Unexpected \'' . $char . '\' (expected: \')\' or \',\')';
							$state = -1;
						}
						break;
				} // switch
			} // for
			if (0 != $state) {
				return new \Exception(
					$this->errorMessage(
						SIGMA_CALLBACK_SYNTAX_ERROR,
						(empty($error) ? 'Unexpected end of input' : $error)
						. ' in ' . $regs[0] . substr($template, 0, $i)
					),
					SIGMA_CALLBACK_SYNTAX_ERROR
				);

			} else {
				$funcId   = 'f' . substr(md5(serialize($funcData)), 0, 10);
				$template = substr($template, $i);

				$this->_blocks[$block] .= $this->openingDelimiter . '__function_' . $funcId
					. '__' . $this->closingDelimiter;
				$this->_blockVariables[$block]['__function_' . $funcId . '__'] = true;
				$this->_functions[$block][$funcId] = $funcData;
			}
		} // while
		$this->_blocks[$block] .= $template;
		return SIGMA_OK;
	}

	/**
	 * Replaces an opening delimiter by a special string.
	 *
	 * Used to implement $_options['preserve_data'] logic
	 *
	 * @param string $str String possibly containing opening delimiters
	 *
	 * @access private
	 * @return string
	 */
	function _preserveOpeningDelimiter($str)
	{
		return (false === strpos($str, $this->openingDelimiter))
			? $str
			: str_replace(
				$this->openingDelimiter,
				$this->openingDelimiter . '%preserved%' . $this->closingDelimiter, $str
			);
	}

	/**
	 * Resets the object's properties, used before processing a new template
	 *
	 * @param boolean $removeUnknownVariables remove unknown/unused variables?
	 * @param boolean $removeEmptyBlocks      remove empty blocks?
	 *
	 * @return void
	 * @see    setTemplate(), loadTemplateFile()
	 */
	private function _resetTemplate($removeUnknownVariables = true, $removeEmptyBlocks = true)
	{
		$this->removeUnknownVariables = $removeUnknownVariables;
		$this->removeEmptyBlocks      = $removeEmptyBlocks;
		$this->currentBlock           = '__global__';
		$this->_variables             = array();
		$this->_blocks                = array();
		$this->_children              = array();
		$this->_parsedBlocks          = array();
		$this->_touchedBlocks         = array();
		$this->_functions             = array();
		$this->flagGlobalParsed       = false;
	}
}
?>