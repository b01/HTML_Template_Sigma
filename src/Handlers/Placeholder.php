<?php namespace Kshabazz\Sigma\Handlers;

use Kshabazz\Sigma\SigmaException;

/**
 * Class Placeholder
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Placeholder
{
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
		$ret = array();
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
	 * Callback generating a placeholder to replace an <!-- INCLUDE filename --> statement
	 *
	 * @param array $matches Matches from preg_replace_callback() call
	 *
	 * @access private
	 * @return string  a placeholder
	 */
	function _makeTrigger($matches)
	{
		$name = 'trigger_' . substr(md5($matches[1] . ' ' . uniqid($this->_triggerBlock)), 0, 10);
		$this->_triggers[$this->_triggerBlock][$name] = $matches[1];
		return $this->openingDelimiter . $name . $this->closingDelimiter;
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