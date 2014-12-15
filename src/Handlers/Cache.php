<?php namespace Kshabazz\Sigma\Handlers;

/**
 * Class Cache
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Cache
{
	/**
	 * Checks whether we have a "prepared" template cached.
	 *
	 * If we do not do caching, always returns false
	 *
	 * @param string $filename source filename
	 *
	 * @access private
	 * @return bool yes/no
	 * @see    loadTemplatefile(), addBlockfile(), replaceBlockfile()
	 */
	function _isCached($filename)
	{
		if (null === $this->_cacheRoot) {
			return false;
		}
		$cachedName = $this->_cachedName($filename);
		$sourceName = $this->fileRoot . $filename;
		// if $sourceName does not exist, error will be thrown later
		return false !== ($sourceTime = @filemtime($sourceName)) && @filemtime($cachedName) === $sourceTime;
	} // _isCached


	/**
	 * Loads a "prepared" template file
	 *
	 * @param string $filename    filename
	 * @param string $block       block name
	 * @param string $placeholder variable placeholder to replace by a block
	 *
	 * @access private
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @see    loadTemplatefile(), addBlockfile(), replaceBlockfile()
	 */
	function _getCached($filename, $block = '__global__', $placeholder = '')
	{
		// the same checks are done in addBlock()
		if (!empty($placeholder)) {
			if (isset($this->_blocks[$block])) {
				return new \Exception($this->errorMessage(SIGMA_BLOCK_EXISTS, $block), SIGMA_BLOCK_EXISTS);
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
		}
		if (false === ($content = @file_get_contents($this->_cachedName($filename)))) {
			return new \Exception(
				$this->errorMessage(SIGMA_TPL_NOT_FOUND, $this->_cachedName($filename)), SIGMA_TPL_NOT_FOUND
			);
		}
		$cache = unserialize($content);
		if ('__global__' != $block) {
			$this->_blocks[$block]         = $cache['blocks']['__global__'];
			$this->_blockVariables[$block] = $cache['variables']['__global__'];
			$this->_children[$block]       = $cache['children']['__global__'];
			$this->_functions[$block]      = $cache['functions']['__global__'];
			unset(
				$cache['blocks']['__global__'], $cache['variables']['__global__'],
				$cache['children']['__global__'], $cache['functions']['__global__']
			);
		}
		$this->_blocks         = array_merge($this->_blocks, $cache['blocks']);
		$this->_blockVariables = array_merge($this->_blockVariables, $cache['variables']);
		$this->_children       = array_merge($this->_children, $cache['children']);
		$this->_functions      = array_merge($this->_functions, $cache['functions']);

		// the same thing gets done in addBlockfile()
		if (!empty($placeholder)) {
			$this->_replacePlaceholder($parents[0], $placeholder, $block);
		}
		// pull the triggers, if any
		if (isset($cache['triggers'])) {
			return $this->_pullTriggers($cache['triggers']);
		}
		return SIGMA_OK;
	} // _getCached


	/**
	 * Returns a full name of a "prepared" template file
	 *
	 * @param string $filename source filename, relative to root directory
	 *
	 * @access private
	 * @return string filename
	 */
	function _cachedName($filename)
	{
		if (OS_WINDOWS) {
			$filename = str_replace(array('/', '\\', ':'), array('__', '__', ''), $filename);
		} else {
			$filename = str_replace('/', '__', $filename);
		}
		return $this->_cacheRoot. $filename. '.it';
	} // _cachedName


	/**
	 * Writes a prepared template file.
	 *
	 * Even if NO caching is going on, this method has a side effect: it calls
	 * the _pullTriggers() method and thus loads all files added via <!-- INCLUDE -->
	 *
	 * @param string $filename source filename, relative to root directory
	 * @param string $block    name of the block to save into file
	 *
	 * @access private
	 * @return mixed SIGMA_OK on success, error object on failure
	 */
	function _writeCache($filename, $block)
	{
		// do not save anything if no cache dir, but do pull triggers
		if (null !== $this->_cacheRoot) {
			$cache = array(
				'blocks'    => array(),
				'variables' => array(),
				'children'  => array(),
				'functions' => array()
			);
			$cachedName = $this->_cachedName($filename);
			$this->_buildCache($cache, $block);
			if ('__global__' != $block) {
				foreach (array_keys($cache) as $k) {
					$cache[$k]['__global__'] = $cache[$k][$block];
					unset($cache[$k][$block]);
				}
			}
			if (isset($this->_triggers[$block])) {
				$cache['triggers'] = $this->_triggers[$block];
			}
			$res = $this->_writeFileAtomically($cachedName, serialize($cache));
			if (is_a($res, 'PEAR_Error')) {
				return $res;
			}
			@touch($cachedName, @filemtime($this->fileRoot . $filename));
		}
		// now pull triggers
		if (isset($this->_triggers[$block])) {
			if (SIGMA_OK !== ($res = $this->_pullTriggers($this->_triggers[$block]))) {
				return $res;
			}
			unset($this->_triggers[$block]);
		}
		return SIGMA_OK;
	} // _writeCache

	/**
	 * Atomically writes given content to a given file
	 *
	 * The method first creates a temporary file in the cache directory and
	 * then renames it to the final name. This should prevent creating broken
	 * cache files when there is no space left on device (bug #19220) or reading
	 * incompletely saved files in another process / thread.
	 *
	 * The same idea is used in Twig, Symfony's Filesystem component, etc.
	 *
	 * @param string $fileName Name of the file to write
	 * @param string $content  Content to write
	 *
	 * @access private
	 * @return mixed SIGMA_OK on success, error object on failure
	 * @link http://pear.php.net/bugs/bug.php?id=19220
	 */
	function _writeFileAtomically($fileName, $content)
	{
		$dirName = dirname($fileName);
		$tmpFile = tempnam($dirName, basename($fileName));

		if (function_exists('file_put_contents')) {
			if (false === @file_put_contents($tmpFile, $content)) {
				return new \Exception($this->errorMessage(SIGMA_CACHE_ERROR, $fileName), SIGMA_CACHE_ERROR);
			}

		} else {
			// Fall back to previous solution
			if (!($fh = @fopen($tmpFile, 'wb'))) {
				return new \Exception($this->errorMessage(SIGMA_CACHE_ERROR, $fileName), SIGMA_CACHE_ERROR);
			}
			if (!fwrite($fh, $content)) {
				return new \Exception($this->errorMessage(SIGMA_CACHE_ERROR, $fileName), SIGMA_CACHE_ERROR);
			}
			fclose($fh);
		}

		if (!OS_WINDOWS || version_compare(phpversion(), '5.2.6', '>=')) {
			if (@rename($tmpFile, $fileName)) {
				return SIGMA_OK;
			}

		} else {
			// rename() to an existing file will not work on Windows before PHP 5.2.6,
			// so we need to copy, which isn't that atomic, but better than writing directly to $fileName
			// https://bugs.php.net/bug.php?id=44805
			if (@copy($tmpFile, $fileName) && @unlink($tmpFile)) {
				return SIGMA_OK;
			}
		}

		return new \Exception($this->errorMessage(SIGMA_CACHE_ERROR, $fileName), SIGMA_CACHE_ERROR);
	}

	/**
	 * Builds an array of template data to be saved in prepared template file
	 *
	 * @param array  &$cache template data
	 * @param string $block  block to add to the array
	 *
	 * @access private
	 * @return void
	 */
	function _buildCache(&$cache, $block)
	{
		if (!$this->_options['trim_on_save']) {
			$cache['blocks'][$block] = $this->_blocks[$block];
		} else {
			$cache['blocks'][$block] = preg_replace(
				array('/^\\s+/m', '/\\s+$/m', '/(\\r?\\n)+/'),
				array('', '', "\n"),
				$this->_blocks[$block]
			);
		}
		$cache['variables'][$block] = $this->_blockVariables[$block];
		$cache['functions'][$block] = isset($this->_functions[$block])? $this->_functions[$block]: array();
		if (!isset($this->_children[$block])) {
			$cache['children'][$block] = array();
		} else {
			$cache['children'][$block] = $this->_children[$block];
			foreach (array_keys($this->_children[$block]) as $child) {
				$this->_buildCache($cache, $child);
			}
		}
	}
}
?>