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
	function parse( $block = '__global__', $recursive = FALSE, $fakeParse = FALSE, &$blocks, &$parsedBlocks,
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