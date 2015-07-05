<?php

namespace Kshabazz\Sigma\Parsers;

/**
 * Class Placeholder
 *
 * @package \Kshabazz\Sigma\Parsers
 */
class Placeholder
{
	/** @var string Token that indicates the end of a variable placeholder, a closing curly brace is the default. */
	private $closingDelimiter;

	/** @var string RegExp used to grep function calls in the template (set by the constructor) */
	private $functionRegExp;

	/** @var string Token that indicated the beginning a variable placeholder, an open curly brace is the default. */
	private $openingDelimiter;

	/** @var string RegExp used to find variable placeholder. */
	private $variablesRegExp;

	/**
	 * Constructor
	 *
	 * @param string $variableNameRegExp
	 */
	public function __construct( $variableNameRegExp )
	{
		$functionNameRegEx = '[_a-zA-Z][A-Za-z_0-9]*';

		$this->closingDelimiter = '}';
		$this->openingDelimiter = '{';

		$this->variablesRegExp = \sprintf( '@%s(%s)(:(%s))?%s@sm',
			$this->openingDelimiter,
			$variableNameRegExp,
			$functionNameRegEx,
			$this->closingDelimiter
		);

		$this->functionRegExp = \sprintf( '@func_(%s)\s*\(@sm', $functionNameRegEx );
	}

	/**
	 * Recursively builds a list of all variables within a block.
	 *
	 * Also calls ::buildFunctionList for each block it visits.
	 *
	 * @param string $block block name
	 * @param array $blockVariables
	 * @param array $functions
	 * @param array $blocks
	 * @param array $children
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    _buildFunctionlist()
	 */
	public function parse( &$blockVariables, &$functions, &$blocks, &$children, $block = '__global__' )
	{
		$blockVariables[$block] = [];
		$functions[$block]      = [];

		\preg_match_all($this->variablesRegExp, $blocks[$block], $regs, PREG_SET_ORDER);

		foreach ($regs as $match) {
			$blockVariables[$block][$match[1]] = true;
			if (!empty($match[3])) {
				$funcData = [
					'name' => $match[3],
					'args' => [$this->openingDelimiter . $match[1] . $this->closingDelimiter]
				];
				$funcId   = \substr( \md5(\serialize($funcData)), 0, 10 );

				// update block info
				$blocks[$block] = \str_replace(
					$match[0],
					$this->openingDelimiter . '__function_' . $funcId . '__' . $this->closingDelimiter,
					$blocks[$block]
				);
				$blockVariables[$block]['__function_' . $funcId . '__'] = true;
				$functions[$block][$funcId] = $funcData;
			}
		}

		$res = $this->parseFunctions( $block, $blocks, $blockVariables, $functions );
		if ( SIGMA_OK != $res )
		{
			return $res;
		}

		if ( isset($children[$block]) && \is_array($children[$block]) )
		{
			foreach ( $children[ $block ] as $child => $v )
			{
				$res = $this->parse( $blockVariables, $functions, $blocks, $children, $child );
				if ( SIGMA_OK != $res )
				{
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
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    _buildBlockVariables()
	 */
	private function parseFunctions( $block, &$blocks, &$blockVariables, &$functions )
	{
		$template = $blocks[$block];
		$blocks[$block] = '';

		while (preg_match($this->functionRegExp, $template, $regs)) {
			$blocks[$block] .= substr($template, 0, strpos($template, $regs[0]));
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
				}
			}

			if (0 != $state) {
				return new \Exception(
					$this->errorMessage(
						SIGMA_CALLBACK_SYNTAX_ERROR,
						(empty($error) ? 'Unexpected end of input' : $error)
						. ' in ' . $regs[0] . \substr( $template, 0, $i )
					),
					SIGMA_CALLBACK_SYNTAX_ERROR
				);

			} else {
				$funcId   = 'f' . \substr( \md5(\serialize($funcData)), 0, 10 );
				$template = \substr( $template, $i );

				$blocks[$block] .= $this->openingDelimiter . '__function_' . $funcId
					. '__' . $this->closingDelimiter;
				$blockVariables[$block]['__function_' . $funcId . '__'] = TRUE;
				$functions[$block][$funcId] = $funcData;
			}
		} // while

		$blocks[$block] .= $template;

		return SIGMA_OK;
	}
}