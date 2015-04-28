<?php namespace Kshabazz\Sigma\Handlers;

use Kshabazz\Sigma\SigmaException;

use const
	\Kshabazz\Sigma\SIGMA_OK,
	\Kshabazz\Sigma\SIGMA_BLOCK_EXISTS;

/**
 * Class Block
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Block
{
	/**
	 * Template blocks and their content
	 * @var array
	 * @see _buildBlocks()
	 */
	private $_blocks = array();

	/**
	 * @param $string
	 */
	public function __construct($string)
	{
		$this->_buildBlocks($string);
	}

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
	 * @throws PEAR_Error
	 * @see    addBlockfile()
	 */
	function addBlock($placeholder, $block, $template)
	{
		if (isset($this->_blocks[$block])) {
			return new SigmaException(
				SigmaException::errorMessage(SIGMA_BLOCK_EXISTS, $block),
				SIGMA_BLOCK_EXISTS
			);
		}
		$parents = $this->_findParentBlocks($placeholder);
		if (0 == count($parents)) {
			return new \Exception(
				$this->errorMessage(SIGMA_PLACEHOLDER_NOT_FOUND, $placeholder), SIGMA_PLACEHOLDER_NOT_FOUND
			);

		} elseif (count($parents) > 1) {
			return new \Exception(
				$this->errorMessage(SIGMA_PLACEHOLDER_DUPLICATE, $placeholder), SIGMA_PLACEHOLDER_DUPLICATE
			);
		}

		$list = $this->_buildBlocks(
			"<!-- BEGIN $block -->" .
			preg_replace($this->commentRegExp, '', $template) .
			"<!-- END $block -->"
		);
		if (is_a($list, 'PEAR_Error')) {
			return $list;
		}
		$this->_replacePlaceholder($parents[0], $placeholder, $block);
		return $this->_buildBlockVariables($block);
	}

	/**
	 * Adds a block taken from a file to the template, changing a variable placeholder
	 * to a block placeholder.
	 *
	 * @param string $placeholder name of the variable placeholder
	 * @param string $block       name of the block to be added
	 * @param string $filename    template file that contains the block
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 * @see    addBlock()
	 */
	function addBlockfile($placeholder, $block, $filename)
	{
		if ($this->_isCached($filename)) {
			return $this->_getCached($filename, $block, $placeholder);
		}
		if (false === ($template = @file_get_contents($this->fileRoot . $filename))) {
			return new \Exception($this->errorMessage(SIGMA_TPL_NOT_FOUND, $filename), SIGMA_TPL_NOT_FOUND);
		}
		list($oldTriggerBlock, $this->_triggerBlock) = array($this->_triggerBlock, $block);
		$template = preg_replace_callback($this->includeRegExp, array(&$this, '_makeTrigger'), $template);
		$this->_triggerBlock = $oldTriggerBlock;
		if (SIGMA_OK !== ($res = $this->addBlock($placeholder, $block, $template))) {
			return $res;
		} else {
			return $this->_writeCache($filename, $block);
		}
	}

	/**
	 * Returns a list of blocks within a template.
	 *
	 * If $recursive is false, it returns just a 'flat' array of $parent's
	 * direct subblocks. If $recursive is true, it builds a tree of template
	 * blocks using $parent as root. Tree structure is compatible with
	 * PEAR::Tree's Memory_Array driver.
	 *
	 * @param string $parent    parent block name
	 * @param bool   $recursive whether to return a tree of child blocks (true) or a 'flat' array (false)
	 *
	 * @access public
	 * @return array a list of child blocks
	 * @throws PEAR_Error
	 */
	function getBlockList($parent = '__global__', $recursive = false)
	{
		if (!isset($this->_blocks[$parent])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $parent), SIGMA_BLOCK_NOT_FOUND);
		}
		if (!$recursive) {
			return isset($this->_children[$parent])? array_keys($this->_children[$parent]): array();
		} else {
			$ret = array('name' => $parent);
			if (!empty($this->_children[$parent])) {
				$ret['children'] = array();
				foreach (array_keys($this->_children[$parent]) as $child) {
					$ret['children'][] = $this->getBlockList($child, true);
				}
			}
			return $ret;
		}
	}

	/**
	 * Replaces an existing block with new content.
	 *
	 * This function will replace a block of the template and all blocks
	 * contained in it and add a new block instead. This means you can
	 * dynamically change your template.
	 *
	 * Sigma analyses the way you've nested blocks and knows which block
	 * belongs into another block. This nesting information helps to make the
	 * API short and simple. Replacing blocks does not only mean that Sigma
	 * has to update the nesting information (relatively time consuming task)
	 * but you have to make sure that you do not get confused due to the
	 * template change yourself.
	 *
	 * @param string  $block       name of a block to replace
	 * @param string  $template    new content
	 * @param boolean $keepContent true if the parsed contents of the block should be kept
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 * @see    replaceBlockfile(), addBlock()
	 */
	function replaceBlock($block, $template, $keepContent = false)
	{
		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		// should not throw a error as we already checked for block existance
		$this->_removeBlockData($block, $keepContent);

		$list = $this->_buildBlocks(
			"<!-- BEGIN $block -->" .
			preg_replace($this->commentRegExp, '', $template) .
			"<!-- END $block -->"
		);
		if (is_a($list, 'PEAR_Error')) {
			return $list;
		}
		// renew the variables list
		return $this->_buildBlockVariables($block);
	}

	/**
	 * Replaces an existing block with new content from a file.
	 *
	 * @param string  $block       name of a block to replace
	 * @param string  $filename    template file that contains the block
	 * @param boolean $keepContent true if the parsed contents of the block should be kept
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 * @see    replaceBlock(), addBlockfile()
	 */
	function replaceBlockfile($block, $filename, $keepContent = false)
	{
		if ($this->_isCached($filename)) {
			$res = $this->_removeBlockData($block, $keepContent);
			if (is_a($res, 'PEAR_Error')) {
				return $res;
			} else {
				return $this->_getCached($filename, $block);
			}
		}
		if (false === ($template = @file_get_contents($this->fileRoot . $filename))) {
			return new \Exception($this->errorMessage(SIGMA_TPL_NOT_FOUND, $filename), SIGMA_TPL_NOT_FOUND);
		}
		list($oldTriggerBlock, $this->_triggerBlock) = array($this->_triggerBlock, $block);
		$template = preg_replace_callback($this->includeRegExp, array(&$this, '_makeTrigger'), $template);
		$this->_triggerBlock = $oldTriggerBlock;
		if (SIGMA_OK !== ($res = $this->replaceBlock($block, $template, $keepContent))) {
			return $res;
		} else {
			return $this->_writeCache($filename, $block);
		}
	}

	/**
	 * Checks if the block exists in the template
	 *
	 * @param string $block block name
	 *
	 * @access public
	 * @return bool
	 */
	function blockExists($block)
	{
		return isset($this->_blocks[$block]);
	}
	/**
	 * Sets the name of the current block: the block where variables are added
	 *
	 * @param string $block block name
	 *
	 * @access public
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 */
	function setCurrentBlock($block = '__global__')
	{
		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		$this->currentBlock = $block;
		return SIGMA_OK;
	}

	/**
	 * Returns the current block name
	 *
	 * @return string block name
	 * @access public
	 */
	function getCurrentBlock()
	{
		return $this->currentBlock;
	}

	/**
	 * Preserves the block even if empty blocks should be removed.
	 *
	 * Sometimes you have blocks that should be preserved although they are
	 * empty (no placeholder replaced). Think of a shopping basket. If it's
	 * empty you have to show a message to the user. If it's filled you have
	 * to show the contents of the shopping basket. Now where to place the
	 * message that the basket is empty? It's not a good idea to place it
	 * in you application as customers tend to like unecessary minor text
	 * changes. Having another template file for an empty basket means that
	 * one fine day the filled and empty basket templates will have different
	 * layouts.
	 *
	 * So blocks that do not contain any placeholders but only messages like
	 * "Your shopping basked is empty" are intoduced. Now if there is no
	 * replacement done in such a block the block will be recognized as "empty"
	 * and by default ($removeEmptyBlocks = true) be stripped off. To avoid this
	 * you can call touchBlock()
	 *
	 * @param string $block block name
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 * @see    $removeEmptyBlocks, $_touchedBlocks
	 */
	public function touchBlock($block)
	{
		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		if (isset($this->_hiddenBlocks[$block])) {
			unset($this->_hiddenBlocks[$block]);
		}
		$this->_touchedBlocks[$block] = true;
		return SIGMA_OK;
	}

	/**
	 * Hides the block even if it is not "empty".
	 *
	 * Is somewhat an opposite to touchBlock().
	 *
	 * Consider a block (a 'edit' link for example) that should be visible to
	 * registered/"special" users only, but its visibility is triggered by
	 * some little 'id' field passed in a large array into setVariable(). You
	 * can either carefully juggle your variables to prevent the block from
	 * appearing (a fragile solution) or simply call hideBlock()
	 *
	 * @param string $block block name
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @throws PEAR_Error
	 */
	public function hideBlock($block)
	{
		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		if (isset($this->_touchedBlocks[$block])) {
			unset($this->_touchedBlocks[$block]);
		}
		$this->_hiddenBlocks[$block] = true;
		return SIGMA_OK;
	}

	/**
	 * Returns the name of the (first) block that contains the specified placeholder.
	 *
	 * @param string $placeholder Name of the placeholder you're searching
	 * @param string $block       Name of the block to scan. If left out (default) all blocks are scanned.
	 *
	 * @access public
	 * @return string Name of the (first) block that contains the specified placeholder.
	 *                If the placeholder was not found an empty string is returned.
	 * @throws PEAR_Error
	 */
	function placeholderExists($placeholder, $block = '')
	{
		if ('' != $block && !isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		if ('' != $block) {
			// if we search in the specific block, we should just check the array
			return isset($this->_blockVariables[$block][$placeholder])? $block: '';
		} else {
			// _findParentBlocks returns an array, we need only the first element
			$parents = $this->_findParentBlocks($placeholder);
			return empty($parents)? '': $parents[0];
		}
	}

	/**
	 * Recursively builds a list of all blocks within the template.
	 *
	 * @param string $string template to be scanned
	 *
	 * @return mixed array of block names on success or error object on failure
	 * @throws PEAR_Error
	 * @see    $_blocks
	 */
	private function _buildBlocks($string)
	{
		$blocks = array();
		if (preg_match_all($this->blockRegExp, $string, $regs, PREG_SET_ORDER)) {
			foreach ($regs as $match) {
				$blockname    = $match[1];
				$blockcontent = $match[2];
				if (isset($this->_blocks[$blockname]) || isset($blocks[$blockname])) {
					return new \Exception(
						$this->errorMessage(SIGMA_BLOCK_DUPLICATE, $blockname), SIGMA_BLOCK_DUPLICATE
					);
				}
				$this->_blocks[$blockname] = $blockcontent;
				$blocks[$blockname] = true;
				$inner              = $this->_buildBlocks($blockcontent);
				if (is_a($inner, 'PEAR_Error')) {
					return $inner;
				}
				foreach ($inner as $name => $v) {
					$pattern     = sprintf('@<!--\s+BEGIN\s+%s\s+-->(.*)<!--\s+END\s+%s\s+-->@sm', $name, $name);
					$replacement = $this->openingDelimiter.'__'.$name.'__'.$this->closingDelimiter;
					$this->_children[$blockname][$name] = true;
					$this->_blocks[$blockname]          = preg_replace(
						$pattern, $replacement, $this->_blocks[$blockname]
					);
				}
			}
		}
		return $blocks;
	}

	/**
	 * Returns the names of the blocks where the variable placeholder appears
	 *
	 * @param string $variable variable name
	 *
	 * @return array block names
	 * @see    addBlock(), addBlockfile(), placeholderExists()
	 */
	private function _findParentBlocks($variable)
	{
		$parents = array();
		foreach ($this->_blockVariables as $blockname => $varnames) {
			if (!empty($varnames[$variable])) {
				$parents[] = $blockname;
			}
		}
		return $parents;
	}

	/**
	 * Recursively removes all data belonging to a block
	 *
	 * @param string  $block       block name
	 * @param boolean $keepContent true if the parsed contents of the block should be kept
	 *
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    replaceBlock(), replaceBlockfile()
	 */
	private function _removeBlockData($block, $keepContent = false)
	{
		if (!isset($this->_blocks[$block])) {
			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
		}
		if (!empty($this->_children[$block])) {
			foreach (array_keys($this->_children[$block]) as $child) {
				$this->_removeBlockData($child, false);
			}
			unset($this->_children[$block]);
		}
		unset($this->_blocks[$block]);
		unset($this->_blockVariables[$block]);
		unset($this->_hiddenBlocks[$block]);
		unset($this->_touchedBlocks[$block]);
		unset($this->_functions[$block]);
		if (!$keepContent) {
			unset($this->_parsedBlocks[$block]);
		}
		return SIGMA_OK;
	}
}
?>