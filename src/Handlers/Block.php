<?php namespace Kshabazz\Sigma\Handlers;

use Kshabazz\Sigma\SigmaException;

use const \Kshabazz\Sigma\OK;

/**
 * Class Block
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Block
{
	/**
	 * Template blocks and their content.
	 *
	 * @var array
	 * @see _buildBlocks()
	 */
	private $_blocks;

	/**
	 * RegExp for matching the block names in the template.
	 * Per default "sm" is used as the regexp modifier, "i" is missing.
	 * That means a case sensitive search is done.
	 * @var string
	 * @see $variablenameRegExp, $openingDelimiter, $closingDelimiter
	 */
	private $blocknameRegExp;

	/**
	 * RegExp used to find blocks and their content.
	 *
	 * @var string
	 * @see HTML_Template_Sigma()
	 */
	private $blockRegExp;

	/**
	 * Variable names that appear in the block
	 *
	 * @var array
	 * @see _buildBlockVariables()
	 */
	private $_blockVariables;

	/**
	 * Inner blocks inside the block
	 * @var array
	 * @see _buildBlocks()
	 */
	private $_children;

	/**
	 * RegExp used to find (and remove) comments in the template
	 * @var string
	 */
	private $commentRegExp;

	/**
	 * First character of a variable placeholder ( _{_VARIABLE} ).
	 *
	 * @var string
	 * @see $closingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	public $openingDelimiter;

	/**
	 * Last character of a variable placeholder ( {VARIABLE_}_ )
	 *
	 * @var string
	 * @see $openingDelimiter, $blocknameRegExp, $variablenameRegExp
	 */
	public $closingDelimiter ;

	/**
	 * Constructor
	 *
	 * @param string $pTemplate
	 * @param string $pOpeningDelimiter
	 * @param string $pClosingDelimiter
	 */
	public function __construct( $pTemplate, $pOpeningDelimiter = '{', $pClosingDelimiter = '}' )
	{
		$this->_blocks = [];
		$this->_blockVariables = [];
		$this->_children = [];
		$this->openingDelimiter = $pOpeningDelimiter;
		$this->closingDelimiter = $pClosingDelimiter;
		$this->blocknameRegExp  = '[0-9A-Za-z_-]+';
		$this->blockRegExp = '@<!--\s+BEGIN\s+('
			. $this->blocknameRegExp
			. ')\s+-->(.*)<!--\s+END\s+\1\s+-->@sm';
		$this->commentRegExp = '#<!--\s+COMMENT\s+-->.*?<!--\s+/COMMENT\s+-->#sm';

		$template = '<!-- BEGIN __global__ -->'
			. \preg_replace( $this->commentRegExp, '', $pTemplate )
			. '<!-- END __global__ -->';

		$this->buildBlocks( $template, $this->openingDelimiter, $this->closingDelimiter );
		// Parse variables in each block.
//		$this->_blockVariables();
	}

	/**
	 * Returns a list of blocks within a template.
	 *
	 * If $recursive is false, it returns just a 'flat' array of $parent's
	 * direct sub-blocks. If $recursive is true, it builds a tree of template
	 * blocks using $parent as root. Tree structure is compatible with
	 * PEAR::Tree's Memory_Array driver.
	 *
	 * @param string $parent parent block name
	 * @param bool $recursive whether to return a tree of child blocks (true) or a 'flat' array (false)
	 *
	 * @access public
	 * @return array a list of child blocks
	 * @throws \Kshabazz\Sigma\SigmaException
	 */
	public function getBlockList( $parent = '__global__', $recursive = FALSE )
	{
		if ( !isset($this->_blocks[$parent]) )
		{
			throw new SigmaException( SigmaException::BLOCK_NOT_FOUND, [$parent] );
		}

		if ( !$recursive )
		{
			return isset( $this->_children[$parent] )? \array_keys( $this->_children[$parent] ): [];
		}
		else
		{
			$ret = ['name' => $parent];
			if (!empty($this->_children[$parent])) {
				$ret['children'] = [];
				foreach (array_keys($this->_children[$parent]) as $child) {
					$ret['children'][] = $this->getBlockList($child, true);
				}
			}
			return $ret;
		}
	}

	/**
	 * Recursively builds a list of all blocks within the template.
	 *
	 * @param string $pTemplate template to be scanned.
	 * @return mixed array of block names on success or error object on failure
	 * @throws \Kshabazz\Sigma\SigmaException
	 * @see $_blocks
	 */
	private function buildBlocks( $pTemplate )
	{
		$blocks = [];
		// When no blocks are found, return immediately.
		if ( \preg_match_all($this->blockRegExp, $pTemplate, $regs, PREG_SET_ORDER) < 1 )
		{
			return $blocks;
		}

		foreach ( $regs as $match )
		{
			$blockname = $match[1];
			$blockcontent = $match[2];

			// Don't allow two blocks with the same name.
			if ( isset($this->_blocks[$blockname]) || isset($blocks[$blockname]) )
			{
				throw new SigmaException( SigmaException::BLOCK_DUPLICATE, [$blockname] );
			}

			$this->_blocks[$blockname] = $blockcontent;
			$blocks[$blockname] = true;
			$inner = $this->buildBlocks($blockcontent);

			foreach ($inner as $name => $v)
			{
				$pattern = sprintf( '@<!--\s+BEGIN\s+%s\s+-->(.*)<!--\s+END\s+%s\s+-->@sm', $name, $name );
				$replacement = $this->openingDelimiter . '__' . $name . '__' . $this->closingDelimiter;
				$this->_children[ $blockname ][ $name ] = TRUE;
				$this->_blocks[ $blockname ] = preg_replace(
					$pattern, $replacement, $this->_blocks[ $blockname ]
				);
			}
		}

		return $blocks;
	}
//
//	/**
//	 * Adds a block to the template changing a variable placeholder to a block placeholder.
//	 *
//	 * This means that a new block will be integrated into the template in
//	 * place of a variable placeholder. The variable placeholder will be
//	 * removed and the new block will behave in the same way as if it was
//	 * inside the original template.
//	 *
//	 * The block content must not start with <!-- BEGIN blockname --> and end with
//	 * <!-- END blockname -->, if it does, an error will be thrown.
//	 *
//	 * @param string $placeholder name of the variable placeholder, the name must be unique within the template.
//	 * @param string $block name of the block to be added
//	 * @param string $template content of the block
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws \Kshabazz\Sigma\SigmaException
//	 * @see    addBlockfile()
//	 */
//	public function addBlock( $placeholder, $block, $template )
//	{
////		var_dump($block);
//		// Throw an error if the block already exists.
//		if ( isset($this->_blocks[$block]) )
//		{
//			throw new SigmaException( SigmaException::BLOCK_EXISTS, [$block] );
//		}
//
//		// Find the blocks that contain the placeholder.
//		$parents = $this->_findParentBlocks( $placeholder );
//
////		var_dump($placeholder, $parents);
//		// When none or more then one block contains the placeholder, throw an error.
//		if ( 0 == count($parents) )
//		{
//			throw new SigmaException( SigmaException::PLACEHOLDER_NOT_FOUND, [$placeholder] );
//		}
//		elseif (count($parents) > 1)
//		{
//			throw new SigmaException( SigmaException::PLACEHOLDER_DUPLICATE, $placeholder );
//		}
//
//		$list = $this->_buildBlocks(
//			"<!-- BEGIN $block -->" .
//			preg_replace($this->commentRegExp, '', $template) .
//			"<!-- END $block -->"
//		);
//		if (is_a($list, 'PEAR_Error')) {
//			return $list;
//		}
//		$this->_replacePlaceholder($parents[0], $placeholder, $block);
//		return $this->_buildBlockVariables($block);
//	}

//	/**
//	 * Adds a block taken from a file to the template, changing a variable placeholder
//	 * to a block placeholder.
//	 *
//	 * @param string $placeholder name of the variable placeholder
//	 * @param string $block       name of the block to be added
//	 * @param string $filename    template file that contains the block
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 * @see    addBlock()
//	 */
//	function addBlockfile($placeholder, $block, $filename)
//	{
//		if ($this->_isCached($filename)) {
//			return $this->_getCached($filename, $block, $placeholder);
//		}
//		if (false === ($template = @file_get_contents($this->fileRoot . $filename))) {
//			return new \Exception($this->errorMessage(SIGMA_TPL_NOT_FOUND, $filename), SIGMA_TPL_NOT_FOUND);
//		}
//		list($oldTriggerBlock, $this->_triggerBlock) = array($this->_triggerBlock, $block);
//		$template = preg_replace_callback($this->includeRegExp, array(&$this, '_makeTrigger'), $template);
//		$this->_triggerBlock = $oldTriggerBlock;
//		if (SIGMA_OK !== ($res = $this->addBlock($placeholder, $block, $template))) {
//			return $res;
//		} else {
//			return $this->_writeCache($filename, $block);
//		}
//	}
//
//	/**
//	 * Replaces an existing block with new content.
//	 *
//	 * This function will replace a block of the template and all blocks
//	 * contained in it and add a new block instead. This means you can
//	 * dynamically change your template.
//	 *
//	 * Sigma analyses the way you've nested blocks and knows which block
//	 * belongs into another block. This nesting information helps to make the
//	 * API short and simple. Replacing blocks does not only mean that Sigma
//	 * has to update the nesting information (relatively time consuming task)
//	 * but you have to make sure that you do not get confused due to the
//	 * template change yourself.
//	 *
//	 * @param string  $block       name of a block to replace
//	 * @param string  $template    new content
//	 * @param boolean $keepContent true if the parsed contents of the block should be kept
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 * @see    replaceBlockfile(), addBlock()
//	 */
//	function replaceBlock($block, $template, $keepContent = false)
//	{
//		if (!isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		// should not throw a error as we already checked for block existance
//		$this->_removeBlockData($block, $keepContent);
//
//		$list = $this->_buildBlocks(
//			"<!-- BEGIN $block -->" .
//			preg_replace($this->commentRegExp, '', $template) .
//			"<!-- END $block -->"
//		);
//		if (is_a($list, 'PEAR_Error')) {
//			return $list;
//		}
//		// renew the variables list
//		return $this->_buildBlockVariables($block);
//	}
//
//	/**
//	 * Replaces an existing block with new content from a file.
//	 *
//	 * @param string  $block       name of a block to replace
//	 * @param string  $filename    template file that contains the block
//	 * @param boolean $keepContent true if the parsed contents of the block should be kept
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 * @see    replaceBlock(), addBlockfile()
//	 */
//	function replaceBlockfile($block, $filename, $keepContent = false)
//	{
//		if ($this->_isCached($filename)) {
//			$res = $this->_removeBlockData($block, $keepContent);
//			if (is_a($res, 'PEAR_Error')) {
//				return $res;
//			} else {
//				return $this->_getCached($filename, $block);
//			}
//		}
//		if (false === ($template = @file_get_contents($this->fileRoot . $filename))) {
//			return new \Exception($this->errorMessage(SIGMA_TPL_NOT_FOUND, $filename), SIGMA_TPL_NOT_FOUND);
//		}
//		list($oldTriggerBlock, $this->_triggerBlock) = array($this->_triggerBlock, $block);
//		$template = preg_replace_callback($this->includeRegExp, array(&$this, '_makeTrigger'), $template);
//		$this->_triggerBlock = $oldTriggerBlock;
//		if (SIGMA_OK !== ($res = $this->replaceBlock($block, $template, $keepContent))) {
//			return $res;
//		} else {
//			return $this->_writeCache($filename, $block);
//		}
//	}
//
//	/**
//	 * Checks if the block exists in the template
//	 *
//	 * @param string $block block name
//	 *
//	 * @access public
//	 * @return bool
//	 */
//	function blockExists($block)
//	{
//		return isset($this->_blocks[$block]);
//	}
//	/**
//	 * Sets the name of the current block: the block where variables are added
//	 *
//	 * @param string $block block name
//	 *
//	 * @access public
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 */
//	function setCurrentBlock($block = '__global__')
//	{
//		if (!isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		$this->currentBlock = $block;
//		return SIGMA_OK;
//	}
//
//	/**
//	 * Returns the current block name
//	 *
//	 * @return string block name
//	 * @access public
//	 */
//	function getCurrentBlock()
//	{
//		return $this->currentBlock;
//	}
//
//	/**
//	 * Preserves the block even if empty blocks should be removed.
//	 *
//	 * Sometimes you have blocks that should be preserved although they are
//	 * empty (no placeholder replaced). Think of a shopping basket. If it's
//	 * empty you have to show a message to the user. If it's filled you have
//	 * to show the contents of the shopping basket. Now where to place the
//	 * message that the basket is empty? It's not a good idea to place it
//	 * in you application as customers tend to like unecessary minor text
//	 * changes. Having another template file for an empty basket means that
//	 * one fine day the filled and empty basket templates will have different
//	 * layouts.
//	 *
//	 * So blocks that do not contain any placeholders but only messages like
//	 * "Your shopping basked is empty" are intoduced. Now if there is no
//	 * replacement done in such a block the block will be recognized as "empty"
//	 * and by default ($removeEmptyBlocks = true) be stripped off. To avoid this
//	 * you can call touchBlock()
//	 *
//	 * @param string $block block name
//	 *
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 * @see    $removeEmptyBlocks, $_touchedBlocks
//	 */
//	public function touchBlock($block)
//	{
//		if (!isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		if (isset($this->_hiddenBlocks[$block])) {
//			unset($this->_hiddenBlocks[$block]);
//		}
//		$this->_touchedBlocks[$block] = true;
//		return SIGMA_OK;
//	}
//
//	/**
//	 * Hides the block even if it is not "empty".
//	 *
//	 * Is somewhat an opposite to touchBlock().
//	 *
//	 * Consider a block (a 'edit' link for example) that should be visible to
//	 * registered/"special" users only, but its visibility is triggered by
//	 * some little 'id' field passed in a large array into setVariable(). You
//	 * can either carefully juggle your variables to prevent the block from
//	 * appearing (a fragile solution) or simply call hideBlock()
//	 *
//	 * @param string $block block name
//	 *
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @throws PEAR_Error
//	 */
//	public function hideBlock($block)
//	{
//		if (!isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		if (isset($this->_touchedBlocks[$block])) {
//			unset($this->_touchedBlocks[$block]);
//		}
//		$this->_hiddenBlocks[$block] = true;
//		return SIGMA_OK;
//	}
//
//	/**
//	 * Returns the name of the (first) block that contains the specified placeholder.
//	 *
//	 * @param string $placeholder Name of the placeholder you're searching
//	 * @param string $block       Name of the block to scan. If left out (default) all blocks are scanned.
//	 *
//	 * @access public
//	 * @return string Name of the (first) block that contains the specified placeholder.
//	 *                If the placeholder was not found an empty string is returned.
//	 * @throws PEAR_Error
//	 */
//	function placeholderExists($placeholder, $block = '')
//	{
//		if ('' != $block && !isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		if ('' != $block) {
//			// if we search in the specific block, we should just check the array
//			return isset($this->_blockVariables[$block][$placeholder])? $block: '';
//		} else {
//			// _findParentBlocks returns an array, we need only the first element
//			$parents = $this->_findParentBlocks($placeholder);
//			return empty($parents)? '': $parents[0];
//		}
//	}
//
//	/**
//	 * Returns the names of the blocks where the variable placeholder appears
//	 *
//	 * @param string $variable variable name
//	 *
//	 * @return array block names
//	 * @see addBlock(), addBlockfile(), placeholderExists()
//	 */
//	private function _findParentBlocks( $variable )
//	{
//		$parents = [];
//		foreach ( $this->_blockVariables as $blockname => $varnames )
//		{
//			if ( !empty($varnames[$variable]) )
//			{
//				$parents[] = $blockname;
//			}
//		}
//
//		return $parents;
//	}
//
//	/**
//	 * Recursively removes all data belonging to a block
//	 *
//	 * @param string  $block       block name
//	 * @param boolean $keepContent true if the parsed contents of the block should be kept
//	 *
//	 * @return mixed SIGMA_OK on success, error object on failure
//	 * @see    replaceBlock(), replaceBlockfile()
//	 */
//	private function _removeBlockData($block, $keepContent = false)
//	{
//		if (!isset($this->_blocks[$block])) {
//			return new \Exception($this->errorMessage(SIGMA_BLOCK_NOT_FOUND, $block), SIGMA_BLOCK_NOT_FOUND);
//		}
//		if (!empty($this->_children[$block])) {
//			foreach (array_keys($this->_children[$block]) as $child) {
//				$this->_removeBlockData($child, false);
//			}
//			unset($this->_children[$block]);
//		}
//		unset($this->_blocks[$block]);
//		unset($this->_blockVariables[$block]);
//		unset($this->_hiddenBlocks[$block]);
//		unset($this->_touchedBlocks[$block]);
//		unset($this->_functions[$block]);
//		if (!$keepContent) {
//			unset($this->_parsedBlocks[$block]);
//		}
//		return SIGMA_OK;
//	}
}
?>