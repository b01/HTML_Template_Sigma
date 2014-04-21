This package is http://pear.php.net/package/HTML_Template_Sigma and has been migrated from https://svn.php.net/repository/pear/packages/HTML_Template_Sigma

Please report all issues via the PEAR bug tracker: http://pear.php.net/bugs/search.php?cmd=display&package_name[]=HTML_Template_Sigma

Pull requests are welcome.


###Composer Installation

```json
{
    "prefer-stable": true,
    "minimum-stability": "dev",
    "require": {
        "pear/html_template_sigma": "dev-topics/composer-for-pear"
    }
}
```

### Running Unit Test

To test, run either
```
$ phpunit tests/
```
  or
```
$ pear run-tests -r
```


To build, simply
```
$ pear package
```
To install from scratch
```
$ pear install package.xml
```
To upgrade
```
$ pear upgrade -f package.xml
```