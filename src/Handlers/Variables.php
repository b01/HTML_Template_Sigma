<?php namespace Kshabazz\Sigma\Handlers;

/**
 * Class Variables
 *
 * @package Kshabazz\Sigma\Handlers
 */
class Variables
{
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
	private $_globalVariables;

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

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->_globalVariables = [];
	}

	/**
	 * Clears the variables
	 *
	 * Global variables are not affected. The method is useful when you add
	 * a lot of variables via setVariable() and are not sure whether all of
	 * them appear in the block you parse(). If you clear the variables after
	 * parse(), you don't risk them suddenly showing up in other blocks.
	 *
	 * @access public
	 * @return void
	 * @see    setVariable()
	 */
	function clearVariables()
	{
		$this->_variables = array();
	}

	/**
	 * Sets a variable value.
	 *
	 * The function can be used either like setVariable("varname", "value")
	 * or with one array $variables["varname"] = "value" given setVariable($variables)
	 *
	 * If $value is an array ('key' => 'value', ...) then values from that array
	 * will be assigned to template placeholders of the form {variable.key}, ...
	 *
	 * @param string|array $variable variable name or array ('varname' => 'value')
	 * @param string|array $value    variable value if $variable is not an array
	 *
	 * @access public
	 * @return void
	 */
	function setVariable($variable, $value = '')
	{
		if ( \is_array($variable) )
		{
			$this->_variables = array_merge( $this->_variables, $variable );
		}
		elseif ( \is_array($value) )
		{
			$this->_variables = array_merge(
				$this->_variables, $this->_flattenVariables( $variable, $value )
			);
		}
		else
		{
			$this->_variables[$variable] = $value;
		}
	}

	/**
	 * Sets a global variable value.
	 *
	 * @param string|array $variable variable name or array ('varname' => 'value')
	 * @param string|array $value    variable value if $variable is not an array
	 *
	 * @access public
	 * @return void
	 * @see    setVariable()
	 */
	function setGlobalVariable($variable, $value = '')
	{
		if (is_array($variable)) {
			$this->_globalVariables = array_merge($this->_globalVariables, $variable);
		} elseif (is_array($value)) {
			$this->_globalVariables = array_merge(
				$this->_globalVariables, $this->_flattenVariables($variable, $value)
			);
		} else {
			$this->_globalVariables[$variable] = $value;
		}
	}

	/**
	 * Builds the variable names for nested variables
	 *
	 * @param string $name  variable name
	 * @param array  $array value array
	 *
	 * @return array array with 'name.key' keys
	 */
	private function _flattenVariables($name, $array)
	{
		$ret = array();
		foreach ($array as $key => $value) {
			if (is_array($value)) {
				$ret = array_merge($ret, $this->_flattenVariables($name . '.' . $key, $value));
			} else {
				$ret[$name . '.' . $key] = $value;
			}
		}
		return $ret;
	}
}
?>