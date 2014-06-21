<?php namespace Sigma;
/**
 * Unit tests for HTML_Template_Sigma
 *
 * PHP versions 4 and 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category    HTML
 * @package     HTML_Template_Sigma
 * @author      Alexey Borzov <avb@php.net>
 * @copyright   2001-2007 The PHP Group
 * @license     http://www.php.net/license/3_01.txt PHP License 3.01
 * @version     CVS: $Id$
 * @link        http://pear.php.net/package/HTML_Template_Sigma
 * @ignore
 */

/**
 * Test case for fixed bugs
 *
 * @category    HTML
 * @package     HTML_Template_Sigma
 * @author      Alexey Borzov <avb@php.net>
 * @version     @package_version@
 * @ignore
 */
class BugTest extends \PHPUnit_Framework_TestCase
{
    /** @var \HTML_Template_Sigma */
    private $tpl;
    /** @var  string */
    private $templatePath;

    function setUp()
    {
        $this->templatePath = $GLOBALS['_HTML_Template_Sigma_templates_dir'];
        $this->tpl = new \HTML_Template_Sigma($this->templatePath);
    }

    function test_bug_6902()
    {
        if (!OS_WINDOWS) {
            $this->markTestSkipped('Test for a Windows-specific bug');
        }
        // realpath() on windows will return full path including drive letter
        $this->tpl->setRoot('');
        $this->tpl->setCacheRoot($GLOBALS['_HTML_Template_Sigma_cache_dir']);
        $result = $this->tpl->loadTemplatefile(realpath($this->templatePath) . DIRECTORY_SEPARATOR . 'loadtemplatefile.html');
        if (is_a($result, 'PEAR_Error')) {
            $this->assertTrue(false, 'Error loading template file: '. $result->getMessage());
        }
        $this->assertEquals('A template', trim($this->tpl->get()));
        $result = $this->tpl->loadTemplatefile(realpath($this->templatePath) . DIRECTORY_SEPARATOR . 'loadtemplatefile.html');
        if (is_a($result, 'PEAR_Error')) {
            $this->assertTrue(false, 'Error loading template file: '. $result->getMessage());
        }
        $this->assertEquals('A template', trim($this->tpl->get()));
    }
}
?>
