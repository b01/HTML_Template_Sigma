<?php
require_once __DIR__ . '/../vendor/autoload.php';
$GLOBALS['_HTML_Template_Sigma_cache_dir'] = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
$GLOBALS['_HTML_Template_Sigma_templates_dir'] = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
define('OS_WINDOWS', strtoupper(substr(PHP_OS, 0, 3)));
?>