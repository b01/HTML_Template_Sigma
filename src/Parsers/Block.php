<?php
/**
 * Contains methods for parsing block from templates.
 *
 * @copy 2015 Khalifah Khalil Shabazz
 */

namespace Kshabazz\Sigma\Parsers;
use Kshabazz\Sigma\SigmaException;

/**
 * Class Block
 *
 * @package Kshabazz\Sigma\Parsers
 */
class Block
{
	/** @var string Token that indicates the end of a variable placeholder, a closing curly brace is the default. */
	private $closingDelimiter;

	/** @var string RegExp used to find (and remove) comments in the template */
	private $commentRegExp = '#<!--\s+COMMENT\s+-->.*?<!--\s+/COMMENT\s+-->#sm';

	/** @var string Token that indicated the beginning a variable placeholder, an open curly brace is the default. */
	private $openingDelimiter;

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->closingDelimiter = '}';
		$this->openingDelimiter = '{';
	}

//	/**
//	 * Adds a block to the template changing a variable placeholder to a block placeholder.
//	 *
//	 * This means that a new block will be integrated into the template in
//	 * place of a variable placeholder. The variable placeholder will be
//	 * removed and the new block will behave in the same way as if it was
//	 * inside the original template.
//	 *
//	 * The block content must not start with <!-- BEGIN blockName --> nor end with
//	 * <!-- END blockName -->, if it does the error will be thrown.
//	 *
//	 * @param string $placeholder name of the variable placeholder, the name must be unique within the template.
//	 * @param string $block name of the block to be added
//	 * @param string $pContent content of the block
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws SigmaException
//	 * @see addBlockfile()
//	 */
//	function addBlock( $placeholder, $block, $pContent )
//	{
//		// Don't replace a block that already exists, there is a separate
//		// method for that.
//		if ( isset($this->_blocks[$block]) )
//		{
//			return new SigmaException( SigmaException::BLOCK_EXISTS, [$block] );
//		}
//
//		$parents = $this->_findParentBlocks($placeholder);
//		if ( \count($parents) === 0 )
//		{
//			return new SigmaException( SigmaException::PLACEHOLDER_NOT_FOUND, [$placeholder] );
//		}
//		// I guess this occurs when you use the same placeholder name in
//		// multiple blocks, in the same template.
//		// TODO: add unit test for this.
//		else if ( \count($parents) > 1 )
//		{
//			return new SigmaException( SigmaException::PLACEHOLDER_DUPLICATE, [$placeholder] );
//		}
//
//		// Remove all HTML comments.
//		$content = \preg_replace( $this->commentRegExp, '', $pContent );
//		// Format the content as a block.
//		$content = \sprintf( "<!-- BEGIN {$block} -->%s<!-- END {$block} -->", $content );
//		// Add the block.
//		$this->blocks->buildBlocks( $content );
//
//		// Update blocks.
//		$this->_blocks = $this->blocks->getBlocks();
//		$this->_children = $this->blocks->getChildrenData();
//
//		// Find the parent block of the placeholder so that it bc
//		$this->_replacePlaceholder($parents[0], $placeholder, $block);
//
//		return $this->placeholder->parse( $this->_blockVariables, $this->_functions,
//			$this->_blocks, $this->_children,$block );
//	}

	/**
	 * Adds a block to the template changing a variable placeholder to a block placeholder.
	 *
	 * This means that a new block will be integrated into the template in
	 * place of a variable placeholder. The variable placeholder will be
	 * removed and the new block will behave in the same way as if it was
	 * inside the original template.
	 *
	 * The block content must not start with <!-- BEGIN blockname --> and end with
	 * <!-- END blockname -->, if it does the error will be thrown.
	 *
	 * @param string $placeholder name of the variable placeholder, the name must be unique within the template.
	 * @param string $block       name of the block to be added
	 * @param string $template    content of the block
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws \Kshabazz\Sigma\SigmaException
	 * @see    addBlockfile()
	 */
	function addBlock( $placeholder, $block, $template, &$blocks, &$blockVariables )
	{
		// Cannot add a block that already exists.
		if (isset($blocks[$block]))
		{
			throw new SigmaException( SigmaException::BLOCK_EXISTS, [$block] );
		}
//
//		$this->_buildBlocks(
//			"<!-- BEGIN {$block} -->" .
//			preg_replace($this->commentRegExp, '', $template) .
//			"<!-- END {$block} -->"
//		);

		$this->parse(
			"<!-- BEGIN {$block} -->" .
			preg_replace($this->commentRegExp, '', $template) .
			"<!-- END {$block} -->"
		);

		$this->_replacePlaceholder($parents[0], $placeholder, $block);

		return $this->_buildBlockVariables($block);
	}

	/**
	 * Parses the given block.
	 *
	 * @param string $block         block name
	 * @param bool   $recursive true if the function is called recursively (do not set this to true yourself!)
	 * @param bool   $fakeParse     true if parsing a "hidden" block (do not set this to true yourself!)
	 *
	 * @return bool whether the block was "empty"
	 * @access public
	 * @see    parseCurrentBlock()
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function parse( $block = '__global__', $recursive = FALSE, $fakeParse = FALSE, &$blocks, &$parsedBlocks,
					&$blockVariables, &$variables, &$children, &$hiddenBlocks, &$globalVariables, &$functions,
					&$options, &$removeEmptyBlocks, &$touchedBlocks, &$callback)
	{
		// Use this to track all of the variables during recursive calls.
		static $vars;

		// When the block does not exist, let it be known immediately.
		if ( !\array_key_exists($block, $blocks) )
		{
			throw new SigmaException( SigmaException::BLOCK_NOT_FOUND, [$block] );
		}

		// Initialize a block that has not been parsed.
		if (!isset($parsedBlocks[$block])) {
			$parsedBlocks[$block] = '';
		}

		if (!$recursive) {
			$vars = [];
		}
		// block is not empty if its local var is substituted
		$empty = true;
		foreach ($blockVariables[$block] as $allowedvar => $v) {
			if (isset($variables[$allowedvar])) {
				$vars[$this->openingDelimiter . $allowedvar . $this->closingDelimiter] = $variables[$allowedvar];
				$empty = false;
				// vital for checking "empty/nonempty" status
				unset($variables[$allowedvar]);
			}
		}

		$outer = $blocks[$block];

		// processing of the inner blocks.
		$this->parseChildBlocks( $block, $children, $outer, $fakeParse, $empty, $blocks, $parsedBlocks, $blockVariables,
			$variables, $hiddenBlocks, $globalVariables, $functions, $options, $removeEmptyBlocks,
			$touchedBlocks, $callback );

		// add "global" variables to the static array
		foreach ($globalVariables as $allowedvar => $value) {
			if (isset($blockVariables[$block][$allowedvar])) {
				$vars[$this->openingDelimiter . $allowedvar . $this->closingDelimiter] = $value;
			}
		}
		// if we are inside a hidden block, don't bother
		if ( !$fakeParse )
		{
			// When there are global variables to replace and there is no
			// recursive call to be made or there are functions replacements.
			// setup for those replacements to be done.
			if ( \count($vars) > 0 && (!$recursive || !empty($functions[$block])))
			{
				$varKeys = \array_keys( $vars );
				if ( $options['preserve_data'] )
				{
					$varValues = \array_map(
						[$this, 'preserveOpeningDelimiter'],
						\array_values( $vars )
					);
				}
				else
				{
					$varValues = \array_values( $vars );
				}
			}

			// check whether the block is considered "empty" and append parsed content if not
			if (!$empty || '__global__' == $block
				|| !$removeEmptyBlocks || isset($touchedBlocks[$block])
			) {
				// perform callbacks
				if (!empty($functions[$block])) {
					foreach ($functions[$block] as $id => $data) {
						$placeholder = $this->openingDelimiter . '__function_' . $id . '__' . $this->closingDelimiter;
						// do not waste time calling function more than once
						if (!isset($vars[$placeholder])) {
							$args         = [];
							$preserveArgs = !empty($callback[$data['name']]['preserveArgs']);
							foreach ($data['args'] as $arg) {
								$args[] = (empty($varKeys) || $preserveArgs)
									? $arg
									: \str_replace($varKeys, $varValues, $arg);
							}
							if (isset($callback[$data['name']]['data'])) {
								$res = \call_user_func_array($callback[$data['name']]['data'], $args);
							} else {
								$res = isset($args[0])? $args[0]: '';
							}
							$outer = \str_replace($placeholder, $res, $outer);
							// save the result to variable cache, it can be requested somewhere else
							$vars[$placeholder] = $res;
						}
					}
				}
				// substitute variables only on non-recursive call, thus all
				// variables from all inner blocks get substituted
				if (!$recursive && !empty($varKeys)) {
					$outer = \str_replace($varKeys, $varValues, $outer);
				}

				$parsedBlocks[$block] .= $outer;
				if (isset($touchedBlocks[$block])) {
					unset($touchedBlocks[$block]);
				}
			}
		}

		// When it is the global block flag it.
		if ( \strcmp('__global__', $block) === 0 )
		{
			$this->flagGlobalParsed = TRUE;
		}

		return $empty;
	}

	/**
	 * Parse child blocks
	 *
	 * @param $block
	 * @param $outer
	 * @param $fakeParse
	 * @param $empty
	 * @return boolean
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function parseChildBlocks( $block, &$children, &$outer, $fakeParse, &$empty, &$blocks, &$parsedBlocks,
		&$blockVariables, &$variables, &$hiddenBlocks, &$globalVariables, &$functions,
		&$options, &$removeEmptyBlocks, &$touchedBlocks, &$callback )
	{
		if ( !\array_key_exists($block, $children) )
		{
			return FALSE;
		}

		$childBlocks = $children[$block];
		foreach ( $childBlocks as $innerblock => $v )
		{
			$placeholder = $this->openingDelimiter . '__' . $innerblock . '__' . $this->closingDelimiter;

			if ( isset( $hiddenBlocks[ $innerblock ] ) )
			{
				// don't bother actually parsing this inner block; but we _have_
				// to go through its local vars to prevent problems on next iteration
				$this->parse( $innerblock, TRUE, TRUE, $blocks, $parsedBlocks,
					$blockVariables, $variables, $children, $hiddenBlocks, $globalVariables, $functions,
					$options, $removeEmptyBlocks, $touchedBlocks, $callback );

				unset( $hiddenBlocks[ $innerblock ] );
				$outer = \str_replace( $placeholder, '', $outer );
			}
			else
			{
				$this->parse( $innerblock, TRUE, $fakeParse, $blocks, $parsedBlocks,
					$blockVariables, $variables, $children, $hiddenBlocks, $globalVariables, $functions,
					$options, $removeEmptyBlocks, $touchedBlocks, $callback );

				// block is not empty if its inner block is not empty
				if ( '' != $parsedBlocks[ $innerblock ] )
				{
					$empty = FALSE;
				}

				$outer = \str_replace( $placeholder, $parsedBlocks[ $innerblock ], $outer );
				$parsedBlocks[ $innerblock ] = '';
			}
		}

		return TRUE;
	}

	/**
	 * Returns the names of the blocks where the variable placeholder appears
	 *
	 * @param string $variable variable name
	 *
	 * @access private
	 * @return array block names
	 * @see    addBlock(), addBlockfile(), placeholderExists()
	 */
	function _findParentBlocks($variable, &$blockVariables)
	{
		$parents = [];
		foreach ($blockVariables as $blockname => $varnames) {
			if (!empty($varnames[$variable])) {
				$parents[] = $blockname;
			}
		}
		return $parents;
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
	private function preserveOpeningDelimiter($str)
	{
		return (false === strpos($str, $this->openingDelimiter))
			? $str
			: str_replace(
				$this->openingDelimiter,
				$this->openingDelimiter . '%preserved%' . $this->closingDelimiter, $str
			);
	}
}
?>