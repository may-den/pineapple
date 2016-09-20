# Pineapple: Refactored PEAR and DB

[![Build Status](https://travis-ci.org/wethersherbs/pineapple.svg?branch=master)](https://travis-ci.org/wethersherbs/pineapple)

## Why?

PEAR DB is very old (the copyright range ends 9 years prior to Pineapple's inception), and was obsoleted by MDB2, which has in turn become obsolete. People have code based on these modules, but need an upgrade path that means new code can be developed using modern DB access methods, whilst retaining access for legacy code without opening a second database connection.

This package is a fork of PEAR and DB, heavily refactored. The purpose of it is to provide a method-compatible drop-in replacement, reproducing `PEAR::raiseError` and `PEAR::isError`, and all methods under the `DB` class. Connection-specific drivers are dropped and only a `DoctrineDbal` driver remains, and that must be constructed without a connection-specific DSN and dependency injected with a constructed DBAL connection. It is intended _only_ as a path to Doctrine DBAL migration, to keep legacy systems working whilst retaining a single database connection per application.

The intention is to strip unused methods, clean (remove all warnings and notices), make PSR-2 clean and provide full test coverage of the DB compatibility layer. Global constants will be replaced with class constants, though the compatibility module ([wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat)) will provide global constants that map to class constants for backward compatibility.

It is up to your application to cache the constructed DBAL connection. Please do not use Pineapple DB to retrieve your connection handle.

## How does it work?

In order to facilitate a deep rework without breaking compatibility with applications that use the `DB` and `DB_*` classnames, the code has been refactored to reside within the `Pineapple` namespace. Here is a handy table of the mapping:

| Old class        | New class                    |
|------------------|------------------------------|
| `DB`             | `Pineapple\DB`               |
| `DB_Error`       | `Pineapple\DB\Error`         |
| `DB_result`      | `Pineapple\DB\Result`        |
| `DB_row`         | `Pineapple\DB\Row`           |
| `DB_common`      | `Pineapple\DB\Driver\Common` |
| `PEAR`           | `Pineapple\Util`             |
| `PEAR_Error`     | `Pineapple\Error`            |
| `PEAR_Exception` | `Pineapple\Exception`        |

If possible, it would be beneficial for you to refactor your code to use the new class names and class constants (instead of global constants). However, if refactoring isn't an option, you can use the counterpart module, which provides root namespace class names in the left column of the above table. See [wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat) and load that into your composer configuration to add compatible classes.

## What's changed? What's missing?

- All classes are namespaced. See the table in the previous section for the class name mappings.
- All global variables have now been dropped. This also applies to [wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat). They will not be retained or readded.
- Global constants have been moved to class constants. [wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat) adds global mappings back to class constants if you're using it as a drop-in replacement.
- **All connectivity drivers have been removed**. The only driver provided is `DoctrineDbal` to connect with Doctrine's DBAL (an abstracted PDO).
- All methods in PEAR have been dropped, except for `isError`, `raiseError` and `throwError`. This includes PEAR's pseudo-destructors.
- Compatibility names for legacy constructors and '`_Name`' destructors has been removed and placed in [wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat).
- PEAR & DB Error suppression has been removed.
- Large swathes of code put in place to aid multi-driver compatibility have been removed.
- Methods marked deprecated have been moved to [wethersherbs/pineapple-compat](https://github.com/wethersherbs/pineapple-compat). Try not to use them.
- Spit, polish & PSR-2. Refactoring to support some more modern aspects of PHP (e.g. method statics replaced with class statics).

It would not take a large amount of effort to refactor your code to avoid using the compatibility layer, but it is provided for your convenience.

## Usage

```php
<?php

use Pineapple\DB as PineappleDB;

$db = PineappleDB::connect('DoctrineDbal://');
$db->setConnectionHandle($dbalConn, PineappleDB::parseDSN('mysql://foo:bar@dbhost/dbname');
$result = $db->query('SELECT USER(), DATABASE()');
```

## Test suite

A suite of tests to aid in regression testing whilst refactoring has been built. Every attempt to reach 100% coverage across all methods and classes has been made. Please ensure the test suite is run before submitting patches or pull requests.

A script to run the suite has been put into the project's `composer.json` file. To execute it, run:

```shell
$ composer test
```

Unfortunately, composer squashes the colourised output which helps indicate failures whilst running interactively. If you wish to view the output with colourisation, run:

```shell
$ vendor/bin/phpunit --coverage-html='coverage/' --coverage-text='php://stdout' --colors=auto
```

**We recommend that all changes are tested with PHP versions 5.6.x and 7.x.**

## Credits

The DB authors should take the most credit; the source was forked in its entirety for the refactor.

The following credits were taken from DB:

* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Daniel Convissor [danielc@php.net](mailto:danielc@php.net)

And these from PEAR:

* Sterling Hughes [sterling@php.net](mailto:sterling@php.net)
* Stig Bakken [ssb@php.net](mailto:ssb@php.net)
* Tomas V.V.Cox [cox@idecnet.com](mailto:cox@idecnet.com)
* Greg Beaver [cellog@php.net](mailto:cellog@php.net)

## Who is responsible for this?

* Rob Andrews [rob.andrews@mayden.co.uk](mailto:rob.andrews@mayden.co.uk)

## License

Upstream PEAR is distributed under the BSD 2-clause license. This license is retained. This applies to classes `Util`, `Error` and `Exception`.

Upstream DB is distributed under the PHP License [http://php.net/license/](http://php.net/license/), which is a BSD-style license. This license is retained. This applies to all other classes and includes `DoctrineDbal`, which is derived from `DB_mysqli` (no longer included in this package).

Any additional code or test suites are provided under the PHP License [http://php.net/license/](http://php.net/license/).
