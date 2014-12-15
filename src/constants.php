<?php namespace Kshabazz\Sigma;
/**#@+
 * Error codes
 * @see HTML_Template_Sigma::errorMessage()
 */
const
	SIGMA_BAD_ROOT_ERROR = -15;
/**#@-*/

define('SIGMA_OK',                         1);
define('SIGMA_ERROR',                     -1);
define('SIGMA_TPL_NOT_FOUND',             -2);
define('SIGMA_BLOCK_NOT_FOUND',           -3);
define('SIGMA_BLOCK_DUPLICATE',           -4);
define('SIGMA_CACHE_ERROR',               -5);
define('SIGMA_UNKNOWN_OPTION',            -6);
define('SIGMA_PLACEHOLDER_NOT_FOUND',     -10);
define('SIGMA_PLACEHOLDER_DUPLICATE',     -11);
define('SIGMA_BLOCK_EXISTS',              -12);
define('SIGMA_INVALID_CALLBACK',          -13);
define('SIGMA_CALLBACK_SYNTAX_ERROR',     -14);
define( 'Kshabazz\\Sigma\\OS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)) );
?>