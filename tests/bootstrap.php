<?php
require_once __DIR__ . '/../vendor/autoload.php';
$_HTML_Template_Sigma_cache_dir = __DIR__ . DIRECTORY_SEPARATOR . 'cache';
$_HTML_Template_Sigma_templates_dir = __DIR__ . DIRECTORY_SEPARATOR . 'templates';
$cacheDirExists = is_dir($_HTML_Template_Sigma_cache_dir);
if (!$cacheDirExists)
{
	mkdir($_HTML_Template_Sigma_cache_dir);
}
?>