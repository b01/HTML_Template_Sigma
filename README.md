###Features:

* Nested blocks. Nesting is controlled by the engine.
* Ability to include files from within template: <!-- INCLUDE -->
* Automatic removal of empty blocks and unknown variables (methods to manually tweak/override this are also available)
* Methods for runtime addition and replacement of blocks in templates
* Ability to insert simple function calls into templates: func_uppercase('Hello world!') and to define callback functions for these
* 'Compiled' templates: the engine has to parse a template file using regular expressions to find all the blocks and variable placeholders. This is a very "expensive" operation and is an overkill to do on every page request: templates seldom change on production websites. Thus this feature: an internal representation of the template structure is saved into a file and this file gets loaded instead of the source one on subsequent requests (unless the source changes)
* PHPUnit-based tests to define correct behaviour
* Usage examples for most of the features are available, look in the docs/ directory
* Implementation of Integrated Templates API with template 'compilation' added.

The main new feature in Sigma is the template 'compilation'. Consider the
following: when loading a template file the engine has to parse it using
regular expressions to find all the blocks and variable placeholders. This
is a very "expensive" operation and is definitely an overkill to do on
every page request: templates seldom change on production websites. This is
where the cache kicks in: it saves an internal representation of the
template structure into a file and this file gets loaded instead of the
source one on subsequent requests (unless the source changes, of course).

While HTML_Template_Sigma inherits PHPLib Template's template syntax, it has
an API which is easier to understand. When using HTML_Template_PHPLIB, you
have to explicitly name a source and a target the block gets parsed into.
This gives maximum flexibility but requires full knowledge of template
structure from the programmer.

Integrated Template on the other hands manages block nesting and parsing
itself. The engine knows that inner1 is a child of block2, there's
no need to tell it about this:

```
+ __global__ (hidden and automatically added)
    + block1
    + block2
        + inner1
        + inner2
```

To add content to block1 you simply type:

```php
$tpl->setCurrentBlock("block1");
```

and repeat this as often as needed:

```php
$tpl->setVariable(...);
$tpl->parseCurrentBlock();
```

To add content to block2 you would type something like:

```
$tpl->setCurrentBlock("inner1");
$tpl->setVariable(...);
$tpl->parseCurrentBlock();

$tpl->setVariable(...);
$tpl->parseCurrentBlock();

$tpl->parse("block2");
```

This will result in one repetition of block2 which contains two repetitions
of inner1. inner2 will be removed if $removeEmptyBlock is set to true (which
is the default).

Usage:

```php
$tpl = new HTML_Template_Sigma( [string filerootdir], [string cacherootdir] );

// load a template or set it with setTemplate()
$tpl->loadTemplatefile( string filename [, boolean removeUnknownVariables, boolean removeEmptyBlocks] )

// set "global" Variables meaning variables not beeing within a (inner) block
$tpl->setVariable( string variablename, mixed value );

// like with the HTML_Template_PHPLIB there's a second way to use setVariable()
$tpl->setVariable( array ( string varname => mixed value ) );

// Let's use any block, even a deeply nested one
$tpl->setCurrentBlock( string blockname );

// repeat this as often as you need it.
$tpl->setVariable( array ( string varname => mixed value ) );
$tpl->parseCurrentBlock();

// get the parsed template or print it: $tpl->show()
$html = $tpl->get();
```

**To test, run**
```bash
$ composer.phar install
$ ./vendor/bin/phpunit
```

**To Install, add the following to your composer.json**
```json
{
	"minimum-stability": "dev",
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/b01/sigma"
        }
    ],
    "require": {
        "kshabazz/sigma": "dev-master"
    }
}
```