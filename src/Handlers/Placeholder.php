<?php namespace Kshabazz\Sigma\Handlers;

use Kshabazz\Sigma\SigmaException;

/**
 * Class Placeholder
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Placeholder
{
	/** @var Block */
	private $blocks;

	/** @var array Variable names that appear in the block. */
	private $_blockVariables;

	/** @var string Closing token of a variable placeholder ( {VARIABLE_}_ ). */
	private $closingDelimiter;

	/** @var string Regular expression for parsing function names from a template. */
	private $functionNameRegExp;

	/** @var array List of functions found in the template. */
	private $_functions;

	/** @var string Beginning token of a variable placeholder ( _{_VARIABLE} ). */
	private $openingDelimiter;

	/** @var string RegExp matching a variable placeholder in the template. Per default "sm" is used as the regexp
	 * modifier, "i" is missing. That means a case sensitive search is done.
	 */
	private $variableNameRegExp = '[0-9A-Za-z._-]+';

	/** @var string RegExp used to find variable placeholder, filled by the constructor. Looks somewhat like:
	 * @(delimiter varName delimiter)@
	 */
	private $variablesRegExp;

	/**
	 * Construct
	 *
	 * @param \Kshabazz\Sigma\Handlers\Block $blocks
	 * @param string $block First block to begin parsing, will be drilled down recursively.
	 */
	public function __construct( Block $blocks, $block = '__global__' )
	{
		$this->blocks = $blocks;
		$this->_blockVariables = [];
		$this->closingDelimiter = '}';
		$this->functionNameRegExp = '[_a-zA-Z][A-Za-z_0-9]*';
		$this->_functions = [];
		$this->openingDelimiter = '{';

		// BEGIN defaults
		$this->variablesRegExp = \sprintf( '@%s(%s)(:(%s))?%s@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp,
			$this->functionNameRegExp,
			$this->closingDelimiter
		);

		$this->removeVariablesRegExp = \sprintf( '@%s\s*(%s)\s*%s@sm',
			$this->openingDelimiter,
			$this->variableNameRegExp,
			$this->closingDelimiter
		);

		$this->functionRegExp = \sprintf( '@func_(%s)\s*\(@sm', $this->functionNameRegExp );

		$this->buildBlockVariables( $block, $this->blocks );
	}

	/**
	 * Recursively builds a list of all variables within a block.
	 *
	 * @uses Placeholder::_buildFunctionlist() Called on each block it visits.
	 *
	 * @param string $block block name
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    _buildFunctionlist()
	 * @todo Rename to parser()
	 */
	public function buildBlockVariables($block = '__global__')
	{
		$this->_blockVariables[$block] = [];
		$this->_functions[$block] = [];

		\preg_match_all( $this->variablesRegExp, $this->blocks->getBlocks($block), $regs, \PREG_SET_ORDER );
//		\preg_match_all( $this->variablesRegExp, $this->_blocks[$block], $regs, \PREG_SET_ORDER );

		foreach ($regs as $match) {
			$this->_blockVariables[$block][$match[1]] = true;
			if (!empty($match[3])) {
				$funcData = [
					'name' => $match[3],
					'args' => [$this->openingDelimiter . $match[1] . $this->closingDelimiter]
				];

				// Devise a unique name for the function.
				$funcId = \substr( \md5(\serialize($funcData)), 0, 10 );

				// update block info
				$this->_blocks[$block] = \str_replace(
					$match[0],
					$this->openingDelimiter . '__function_' . $funcId . '__' . $this->closingDelimiter,
					$this->_blocks[$block]
				);
				$this->_blockVariables[$block]['__function_' . $funcId . '__'] = true;
				$this->_functions[$block][$funcId] = $funcData;
			}
		}
		// Parse functions.
		if (SIGMA_OK != ($res = $this->_buildFunctionlist($block))) {
			return $res;
		}
		if (isset($this->_children[$block]) && \is_array($this->_children[$block])) {
			foreach ($this->_children[$block] as $child => $v) {
				if (SIGMA_OK != ($res = $this->buildBlockVariables($child))) {
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
	 * @access private
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
			$funcData = [
				'name' => $regs[1],
				'args' => []
			];
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
	} // end func _buildFunctionlist

	/**
	 * Returns a list of placeholders within a block.
	 *
	 * Only 'normal' placeholders are returned, not auto-created ones.
	 *
	 * @param string $block block name
	 *
	 * @access
	 * @return array a list of placeholders
	 * @throws SigmaException
	 */
	public function getPlaceholderList($block = '__global__')
	{
		if (!isset($this->_blocks[$block])) {
			return new SigmaException($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		$ret = [];
		foreach ($this->_blockVariables[$block] as $var => $v) {
			if ('__' != substr($var, 0, 2) || '__' != substr($var, -2)) {
				$ret[] = $var;
			}
		}
		return $ret;
	}

	/**
	 * Replaces a variable placeholder by a block placeholder.
	 *
	 * Of course, it also updates the necessary arrays
	 *
	 * @param string $parent      name of the block containing the placeholder
	 * @param string $placeholder variable name
	 * @param string $block       block name
	 *
	 * @access private
	 * @return void
	 * TODO: change name to convertPlaceholderToBlock
	 */
	function _replacePlaceholder($parent, $placeholder, $block)
	{
		$this->_children[$parent][$block] = true;
		$this->_blockVariables[$parent]['__'.$block.'__'] = true;
		$this->_blocks[$parent] = str_replace(
			$this->openingDelimiter . $placeholder . $this->closingDelimiter,
			$this->openingDelimiter . '__' . $block . '__' . $this->closingDelimiter,
			$this->_blocks[$parent]
		);
		unset($this->_blockVariables[$parent][$placeholder]);
	}

	/**
	 * Replaces the "trigger" placeholders by the matching file contents.
	 *
	 * @param array $triggers array ('trigger placeholder' => 'filename')
	 *
	 * @access private
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see _makeTrigger(), addBlockfile()
	 */
	function _pullTriggers($triggers)
	{
		foreach ($triggers as $placeholder => $filename) {
			if (SIGMA_OK !== ($res = $this->addBlockfile($placeholder, $placeholder, $filename))) {
				return $res;
			}
			// we actually do not need the resultant block...
			$parents = $this->_findParentBlocks('__' . $placeholder . '__');
			// merge current block's children and variables with the parent's ones
			if (isset($this->_children[$placeholder])) {
				$this->_children[$parents[0]] = array_merge(
					$this->_children[$parents[0]], $this->_children[$placeholder]
				);
			}
			$this->_blockVariables[$parents[0]] = array_merge(
				$this->_blockVariables[$parents[0]], $this->_blockVariables[$placeholder]
			);
			if (isset($this->_functions[$placeholder])) {
				$this->_functions[$parents[0]] = array_merge(
					$this->_functions[$parents[0]], $this->_functions[$placeholder]
				);
			}
			// substitute the block's contents into parent's
			$this->_blocks[$parents[0]] = str_replace(
				$this->openingDelimiter . '__' . $placeholder . '__' . $this->closingDelimiter,
				$this->_blocks[$placeholder],
				$this->_blocks[$parents[0]]
			);
			// remove the stuff that is no more needed
			unset(
				$this->_blocks[$placeholder], $this->_blockVariables[$placeholder],
				$this->_children[$placeholder], $this->_functions[$placeholder],
				$this->_children[$parents[0]][$placeholder],
				$this->_blockVariables[$parents[0]]['__' . $placeholder . '__']
			);
		}
		return SIGMA_OK;
	}
}
?>