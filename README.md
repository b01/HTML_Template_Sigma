###Features:
* Nested blocks. Nesting is controlled by the engine.
* Ability to include files from within template: <!-- INCLUDE -->
* Automatic removal of empty blocks and unknown variables (methods to manually tweak/override this are also available)
* Methods for runtime addition and replacement of blocks in templates
* Ability to insert simple function calls into templates: func_uppercase('Hello world!') and to define callback functions for these
* 'Compiled' templates: the engine has to parse a template file using regular expressions to find all the blocks and variable placeholders. This is a very "expensive" operation and is an overkill to do on every page request: templates seldom change on production websites. Thus this feature: an internal representation of the template structure is saved into a file and this file gets loaded instead of the source one on subsequent requests (unless the source changes)
* PHPUnit-based tests to define correct behaviour
* Usage examples for most of the features are available, look in the docs/ directory

This package is http://pear.php.net/package/HTML_Template_Sigma and has been migrated from https://svn.php.net/repository/pear/packages/HTML_Template_Sigma

Please report all issues via the PEAR bug tracker: http://pear.php.net/bugs/search.php?cmd=display&package_name[]=HTML_Template_Sigma

Pull requests are welcome.

* To test, run either
```bash
./vendor/bin/phpunit -c tests/phpunit.xml
```